<?php

namespace App\Http\Controllers\Scoring;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Game\GameController;
use App\Models\Drawing;
use App\Models\GameMatch;
use App\Models\Kudos;
use App\Models\Player;
use App\Models\PlayingHistory;
use App\Models\Score;
use App\Models\SessionModel;
use App\Services\Drawing\AmericanoService;
use App\Services\Drawing\TeamAmericanoService;
use App\Services\Scoring\ScoringService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ScoringController extends Controller
{
    /**
     * Live Match Scoring Console.
     * Menampilkan scoring board aktif berdasarkan data game & round yang dipilih.
     */
    public function live($id = 1)
    {
        $game = $this->getGameData($id);
        $session = SessionModel::findOrFail((int) $id);
        $user = Auth::user();
        $isHost = $this->isHostForSession($session);
        $userRole = $user ? $user->role : 'guest';
        $userPlayerId = null;
        $isPlayer = false;
        if ($user) {
            $player = Player::where('user_id', $user->user_id)->first();
            if ($player) {
                $userPlayerId = $player->player_id;
                $isPlayer = collect($game['participants'] ?? [])->contains(function ($p) use ($userPlayerId) {
                    return ($p['id'] ?? null) == $userPlayerId;
                });
            }
        }

        // Ambil skor tersimpan dari Cache (shared antar semua user)
        $cacheKey = "scoring.game_{$game['id']}";
        $savedScores = Cache::get($cacheKey, []);

        // Jika sesi sudah selesai, langsung arahkan ke halaman recap & podium
        $isSessionFinished = ($session->status_session === 'Finished')
            || ! empty($game['is_finished'])
            || (($savedScores['_meta']['status'] ?? '') === 'finished');

        if ($isSessionFinished) {
            return redirect()->route('scoring.recap', $game['id']);
        }

        // Tentukan round aktif (normalisasi dari query param atau default ronde aktif sesi)
        $rawRound = request('round');
        if ($rawRound) {
            $rNum = preg_replace('/[^0-9]/', '', $rawRound);
            $activeRound = ! empty($rNum) ? "round_{$rNum}" : 'round_1';
        } else {
            $activeRound = $savedScores['_meta']['active_round'] ?? $this->resolveActiveRoundFromDatabase((int) $id) ?? 'round_1';
        }

        $courtIndex = (int) request('court', 0);

        // Deteksi scoring system
        $scoringSystem = ScoringService::detectScoringSystem($game['scoring_system'] ?? '');

        // Validasi urutan akses ronde
        $roundAccess = $this->getRoundAccess($game, $scoringSystem, $savedScores);
        if (! ($roundAccess[$activeRound] ?? false)) {
            $firstAccessibleRound = array_key_last(array_filter($roundAccess)) ?: 'round_1';

            return redirect()->route('scoring.live', [
                'id' => $game['id'],
                'format' => request('format', $game['match_format'] ?? 'Americano'),
                'round' => $firstAccessibleRound,
                'court' => $courtIndex,
            ])->with('warning', 'Set/Ronde saat ini belum selesai. Selesaikan seluruh pertandingan terlebih dahulu.');
        }

        // Otorisasi role: Non-Host dilarang mendahului atau membuka ronde masa depan yang belum diaktifkan Host
        $sessionActiveRound = $savedScores['_meta']['active_round'] ?? 'round_1';
        $reqNum = (int) (preg_replace('/[^0-9]/', '', $activeRound) ?: 1);
        $currActiveNum = (int) (preg_replace('/[^0-9]/', '', $sessionActiveRound) ?: 1);

        if (! $isHost && $reqNum > $currActiveNum) {
            return redirect()->route('scoring.live', [
                'id' => $game['id'],
                'format' => request('format', $game['match_format'] ?? 'Americano'),
                'round' => $sessionActiveRound,
                'court' => $courtIndex,
            ])->with('warning', 'Hanya Host yang dapat melanjutkan ke set/ronde berikutnya.');
        }

        // Jika Host membuka ronde berikutnya yang sudah berhak dibuka, catat ronde aktif baru ke metadata
        if ($isHost && $reqNum > $currActiveNum) {
            $savedScores['_meta'] = array_merge($savedScores['_meta'] ?? [], [
                'active_round' => $activeRound,
                'updated_at' => now()->toDateTimeString(),
            ]);
            Cache::put($cacheKey, $savedScores, now()->addHours(4));
            $this->broadcastRoundAdvancedRealtime((int) $game['id'], $sessionActiveRound, $activeRound);
        }

        // Tandai bahwa scoring untuk ronde ini telah resmi dibuka oleh Host & broadcast ke pemain di Drawing
        if ($isHost) {
            if (empty($savedScores['_meta']["opened_{$activeRound}"])) {
                $savedScores['_meta'] = array_merge($savedScores['_meta'] ?? [], [
                    "opened_{$activeRound}" => true,
                    'updated_at' => now()->toDateTimeString(),
                ]);
                Cache::put($cacheKey, $savedScores, now()->addHours(4));
            }
            $this->broadcastScoringOpenedRealtime((int) $game['id'], $activeRound);
        }

        // Bangun konteks match (tim A, tim B, istirahat, court_name, matches) untuk round aktif & court
        $matchContext = ScoringService::buildMatchContext($game, $activeRound, $courtIndex);
        $courtCount = $matchContext['court_count'] ?? 1;
        $matchKey = ($courtCount > 1) ? "{$activeRound}_court_".($courtIndex + 1) : $activeRound;

        // Sinkronisasi record match dan verifikasi skor resmi dari DB
        $cacheUpdated = false;
        foreach ($matchContext['matches'] as $mIdx => $m) {
            $mKey = ($courtCount > 1) ? "{$activeRound}_court_".($mIdx + 1) : $activeRound;
            if ($isHost) {
                ScoringService::ensureMatchAndParticipants($game['id'], $activeRound, $mIdx, $matchContext);
            }

            $officialDbScore = $this->getOfficialCompletedScoreFromDatabase($game['id'], $activeRound, $mIdx);
            $dbScore = $officialDbScore ?: $this->getScoreFromDatabase($game['id'], $activeRound, $mIdx);
            $cachedScore = $savedScores[$mKey] ?? null;
            $cachedVersion = (int) ($cachedScore['version'] ?? 0);
            $dbVersion = (int) ($dbScore['version'] ?? 0);
            $dbIsNewer = $dbScore && $cachedScore
                && (int) ($dbScore['updated_at_ms'] ?? 0) > (int) ($cachedScore['updated_at_ms'] ?? 0)
                && $dbVersion >= $cachedVersion;
            $dbTerminal = $dbScore && ScoringService::isMatchTerminal($dbScore['status'] ?? '');
            $cachedTerminal = $cachedScore && ScoringService::isMatchTerminal($cachedScore['status'] ?? '');

            $cachedTotalGames = (int) ($cachedScore['games_a'] ?? ($cachedScore['score_a'] ?? 0)) + (int) ($cachedScore['games_b'] ?? ($cachedScore['score_b'] ?? 0));
            $dbTotalGames = (int) ($dbScore['games_a'] ?? ($dbScore['score_a'] ?? 0)) + (int) ($dbScore['games_b'] ?? ($dbScore['score_b'] ?? 0));

            // Authoritative Completed/Stopped DB Score Protection:
            // Jika DB sudah resmi Completed/Stopped, skor DB tidak boleh kalah oleh stale cache dengan skor lebih rendah.
            $shouldUseDb = $dbScore && (
                ! $cachedScore
                || ($dbTerminal && (! $cachedTerminal || $dbTotalGames >= $cachedTotalGames))
                || $dbIsNewer
            );

            if ($shouldUseDb) {
                $savedScores[$mKey] = $dbScore;
                if ($courtCount === 1) {
                    $savedScores[$activeRound] = $dbScore;
                }
                $cacheUpdated = true;
            }
        }
        if ($cacheUpdated) {
            Cache::put($cacheKey, $savedScores, now()->addHours(4));
        }

        $defaultScore = [
            'score_a' => 0,
            'score_b' => 0,
            'point_display_a' => '0',
            'point_display_b' => '0',
            'set_number' => 1,
            'sets_a' => 0,
            'sets_b' => 0,
            'games_a' => 0,
            'games_b' => 0,
            'set_history' => [],
            'idx_a' => 0,
            'idx_b' => 0,
            'is_deuce' => false,
            'advantage' => null,
            'status' => 'in_progress',
            'winner_team' => null,
        ];
        // Multi-court NEVER falls back to $savedScores[$activeRound]
        $matchScore = $savedScores[$matchKey] ?? ($courtCount > 1 ? [] : ($savedScores[$activeRound] ?? []));
        $currentScore = array_merge($defaultScore, $matchScore);
        $pausedPlayerIds = ScoringService::getPausedPlayerIds($session);

        return view('scoring.live', compact(
            'game',
            'matchContext',
            'scoringSystem',
            'currentScore',
            'activeRound',
            'courtIndex',
            'matchKey',
            'savedScores',
            'isHost',
            'userRole',
            'userPlayerId',
            'isPlayer',
            'cacheKey',
            'roundAccess',
            'pausedPlayerIds'
        ));
    }

    /**
     * JSON endpoint: ambil skor terkini dari Cache / DB.
     * Dipakai untuk polling realtime via fetch().
     */
    public function getScore($gameId, $round = 'round_1')
    {
        $sessionExists = Cache::remember("session_exists_{$gameId}", 300, function () use ($gameId) {
            return SessionModel::where('session_id', (int) $gameId)->exists();
        });
        if (! $sessionExists) {
            abort(404, 'Session not found.');
        }

        $cacheKey = "scoring.game_{$gameId}";
        $matchKey = request('match_key');
        $courtIndex = 0;
        if ($matchKey && preg_match('/court_(\d+)/', $matchKey, $mC)) {
            $courtIndex = max(0, ((int) $mC[1]) - 1);
        } elseif ($matchKey) {
            $courtIndex = (int) request('court', 0);
        } elseif (request()->has('court_num')) {
            $courtNum = (int) request('court_num');
            $courtIndex = max(0, $courtNum - 1);
            $matchKey = "{$round}_court_{$courtNum}";
        } elseif (request()->has('court')) {
            $courtIndex = (int) request('court');
            $courtNum = $courtIndex + 1;
            $matchKey = "{$round}_court_{$courtNum}";
        }

        if (! $matchKey) {
            $matchKey = $round;
        }

        $isMultiCourt = str_contains($matchKey, '_court_');

        // 1. Fast Path: Coba ambil dari dedicated match cache key
        $score = Cache::get("scoring.game_{$gameId}.{$matchKey}");
        $scores = null;

        if (! $score) {
            $scores = Cache::get($cacheKey, []);
            $score = $scores[$matchKey] ?? null;

            if (! $score && ! $isMultiCourt && isset($scores["{$matchKey}_court_1"])) {
                $score = $scores["{$matchKey}_court_1"];
            }

            if (! $isMultiCourt && isset($scores["{$matchKey}_court_1"]) && isset($scores[$matchKey])) {
                if ((int) ($scores["{$matchKey}_court_1"]['version'] ?? 0) > (int) ($scores[$matchKey]['version'] ?? 0)) {
                    $score = $scores["{$matchKey}_court_1"];
                }
            }
        }

        // 2. Strategi Rekonsiliasi Database Terkontrol (Throttled & Recovery)
        $isReconnect = request()->boolean('reconnect') || request()->boolean('force_refresh');
        $throttleKey = "scoring.reconcile_throttle.{$gameId}.{$matchKey}";
        $shouldReconcile = false;

        if (! $score || (int) ($score['version'] ?? 0) === 0) {
            // Cache miss / empty: wajib sinkronisasi ke DB
            $shouldReconcile = true;
        } elseif ($isReconnect) {
            // Reconnect / tab visibility: sinkronisasi paksa ke DB
            $shouldReconcile = true;
        } elseif (! Cache::has($throttleKey)) {
            // Throttle periodik (3 detik): hanya 1 request per jendela waktu yang mengecek DB
            Cache::put($throttleKey, true, 3);
            $shouldReconcile = true;
        }

        if ($shouldReconcile) {
            // Anti-stampede mutex: Pastikan hanya 1 request yang menjalankan query rekonsiliasi DB mahal saat thundering herd/reconnect
            $reconcileLock = Cache::lock("scoring.reconcile_lock.{$gameId}.{$matchKey}", 4);
            if ($reconcileLock->get()) {
                try {
                    $dbScore = $this->getScoreFromDatabase($gameId, $round, $courtIndex);
                    if ($dbScore) {
                        $dbVersion = (int) ($dbScore['version'] ?? 0);
                        // Fresh check: Rekonsiliasi DB tidak boleh menimpa cache baru jika host baru saja menyimpan skor in-flight
                        $freshMatchCache = Cache::get("scoring.game_{$gameId}.{$matchKey}");
                        $freshVersion = (int) ($freshMatchCache['version'] ?? ($score['version'] ?? 0));
                        $cachedTotalGames = (int) ($freshMatchCache['games_a'] ?? ($freshMatchCache['score_a'] ?? 0)) + (int) ($freshMatchCache['games_b'] ?? ($freshMatchCache['score_b'] ?? 0));
                        $dbTotalGames = (int) ($dbScore['games_a'] ?? ($dbScore['score_a'] ?? 0)) + (int) ($dbScore['games_b'] ?? ($dbScore['score_b'] ?? 0));
                        $dbCompleted = ($dbScore['status'] ?? '') === 'completed';
                        $cachedCompleted = ($freshMatchCache['status'] ?? '') === 'completed';

                        // Update cache hanya jika data DB lebih baru, selesai, atau mengisi cache kosong
                        if (! $freshMatchCache || ($dbCompleted && (! $cachedCompleted || $dbTotalGames >= $cachedTotalGames)) || ($dbVersion >= $freshVersion)) {
                            $score = $dbScore;
                            Cache::put("scoring.game_{$gameId}.{$matchKey}", $score, now()->addHours(4));

                            $sLock = Cache::lock("scoring.session.{$gameId}", 3);
                            if ($sLock->get()) {
                                try {
                                    $curScores = Cache::get($cacheKey, []);
                                    $curMatchVer = (int) ($curScores[$matchKey]['version'] ?? 0);
                                    if ($dbVersion >= $curMatchVer) {
                                        $curScores[$matchKey] = $score;
                                        if (! $isMultiCourt) {
                                            $curScores[$round] = $score;
                                            $curScores["{$round}_court_1"] = $score;
                                        }
                                        Cache::put($cacheKey, $curScores, now()->addHours(4));
                                    }
                                } finally {
                                    $sLock->release();
                                }
                            }
                        }
                    }
                } finally {
                    $reconcileLock->release();
                }
            } else {
                // Jika sedang ada request lain yang merekonsiliasi DB, gunakan cache terkini
                $score = Cache::get("scoring.game_{$gameId}.{$matchKey}") ?: $score;
            }
        }

        // 3. Fallback hanya untuk single-court jika round key ada
        if (! $score && ! $isMultiCourt) {
            $scores = $scores ?? Cache::get($cacheKey, []);
            if (isset($scores[$round])) {
                $score = $scores[$round];
            }
        }

        // 4. Default state jika masih kosong (DILARANG fallback ke court atau round lain untuk multi-court)
        if (! $score) {
            $score = [
                'version' => 0,
                'updated_at_ms' => (int) round(microtime(true) * 1000),
                'score_a' => 0,
                'score_b' => 0,
                'point_display_a' => '0',
                'point_display_b' => '0',
                'set_number' => 1,
                'sets_a' => 0,
                'sets_b' => 0,
                'games_a' => 0,
                'games_b' => 0,
                'set_history' => [],
                'idx_a' => 0,
                'idx_b' => 0,
                'is_deuce' => false,
                'advantage' => null,
                'scoring_type' => 'total_of_sets',
                'status' => 'in_progress',
                'winner_team' => null,
            ];
        }

        $scores = $scores ?? Cache::get($cacheKey, []);
        $sessionActiveRound = $scores['_meta']['active_round'] ?? $round;
        $sessionStatus = $scores['_meta']['status'] ?? 'in_progress';
        if ($sessionStatus !== 'finished' && $shouldReconcile) {
            $dbStatus = SessionModel::where('session_id', (int) $gameId)->value('status_session');
            if (strtolower((string) $dbStatus) === 'finished') {
                $sessionStatus = 'finished';
                $scores['_meta'] = array_merge($scores['_meta'] ?? [], ['status' => 'finished']);
                Cache::put($cacheKey, $scores, now()->addHours(4));
            }
        }
        $isSessionFinished = ($sessionStatus === 'finished');

        return response()->json([
            'game_id' => (int) $gameId,
            'round' => $round,
            'session_active_round' => (string) $sessionActiveRound,
            'is_session_round_active' => ($round === $sessionActiveRound),
            'session_status' => (string) $sessionStatus,
            'is_session_finished' => $isSessionFinished,
            'recap_url' => route('scoring.recap', $gameId),
            'match_key' => $matchKey,
            'version' => (int) ($score['version'] ?? 0),
            'server_version' => (int) ($score['version'] ?? 0),
            'last_event_id' => (string) ($score['last_event_id'] ?? ''),
            'updated_at_ms' => (int) ($score['updated_at_ms'] ?? 0),
            'score_a' => (int) ($score['games_a'] ?? ($score['score_a'] ?? 0)),
            'score_b' => (int) ($score['games_b'] ?? ($score['score_b'] ?? 0)),
            'point_display_a' => (string) ($score['point_display_a'] ?? ($score['score_a'] ?? '0')),
            'point_display_b' => (string) ($score['point_display_b'] ?? ($score['score_b'] ?? '0')),
            'set_number' => (int) ($score['set_number'] ?? 1),
            'sets_a' => (int) ($score['sets_a'] ?? 0),
            'sets_b' => (int) ($score['sets_b'] ?? 0),
            'games_a' => (int) ($score['games_a'] ?? 0),
            'games_b' => (int) ($score['games_b'] ?? 0),
            'set_history' => $score['set_history'] ?? [],
            'idx_a' => (int) ($score['idx_a'] ?? 0),
            'idx_b' => (int) ($score['idx_b'] ?? 0),
            'is_deuce' => (bool) ($score['is_deuce'] ?? false),
            'advantage' => $score['advantage'] ?? null,
            'scoring_type' => $score['scoring_type'] ?? 'total_of_sets',
            'status' => $score['status'] ?? 'in_progress',
            'winner_team' => $score['winner_team'] ?? null,
            'stop_type' => $score['stop_type'] ?? null,
            'stop_reason' => $score['stop_reason'] ?? null,
            'stopped_at' => $score['stopped_at'] ?? null,
            'withdrawn_player_ids' => $score['withdrawn_player_ids'] ?? [],
            'withdrawn_player_names' => $score['withdrawn_player_names'] ?? [],
        ])->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Simpan/update skor pertandingan (dipanggil via AJAX fetch dari live view).
     * Menyimpan ke Laravel Cache.
     */
    public function updateScore(Request $request)
    {
        $tStart = microtime(true);

        // Auth check: HANYA Host yang dapat mencatat atau mengubah skor
        if (! Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Silakan login terlebih dahulu.',
            ], 401);
        }

        $user = Auth::user();
        $gameId = $request->integer('game_id');
        $session = SessionModel::findOrFail($gameId);
        $isHost = $this->isHostForSession($session);

        if ($user->role === 'host' && ! $isHost) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Host hanya dapat mencatat skor pada sesi miliknya.',
            ], 403);
        }

        // Jika bukan host, cek apakah user merupakan participant/player pada session ini
        if (! $isHost) {
            $isParticipant = false;
            $player = Player::where('user_id', $user->user_id)->first();
            if ($player) {
                $isParticipant = \DB::table('tb_session_player')
                    ->where('session_id', $gameId)
                    ->where('player_id', $player->player_id)
                    ->exists();
            }
            if (! $isParticipant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Hanya Host atau pemain dalam sesi ini yang dapat mencatat skor.',
                ], 403);
            }
        }

        // Tolak penambahan skor jika match sudah berstatus Stopped (sebelum validasi field skor)
        $checkGameId = (int) $request->input('game_id');
        $checkRound = (string) $request->input('round', '');
        $checkCourt = (int) $request->input('court', 0);
        $checkMatchKey = (string) $request->input('match_key', '');
        if (empty($checkMatchKey) && ! empty($checkRound)) {
            $checkCourtNum = max(1, $checkCourt + 1);
            $checkSession = SessionModel::with('courts')->find($checkGameId);
            $checkCourtCount = $checkSession ? max(1, $checkSession->courts->count()) : 1;
            $checkMatchKey = ScoringService::buildMatchKey($checkRound, $checkCourtNum, $checkCourtCount);
        }

        if (! empty($checkGameId)) {
            $cachedScores = Cache::get("scoring.game_{$checkGameId}", []);
            $stoppedReason = null;
            $isStopped = false;
            if (! empty($checkMatchKey) && ($cachedScores[$checkMatchKey]['status'] ?? '') === 'stopped') {
                $isStopped = true;
                $stoppedReason = $cachedScores[$checkMatchKey]['stop_reason'] ?? null;
            }
            if (! $isStopped) {
                $mId = $request->input('match_id');
                if ($mId) {
                    $mObj = GameMatch::find($mId);
                    if ($mObj && strtolower($mObj->status_match ?? '') === 'stopped') {
                        $isStopped = true;
                        $stoppedReason = $mObj->stop_reason ?? null;
                    }
                } elseif (! empty($checkRound)) {
                    $dbOfficial = $this->getOfficialCompletedScoreFromDatabase($checkGameId, $checkRound, $checkCourt);
                    if ($dbOfficial && ($dbOfficial['status'] ?? '') === 'stopped') {
                        $isStopped = true;
                        $stoppedReason = $dbOfficial['stop_reason'] ?? null;
                    }
                }
            }
            if ($isStopped) {
                return response()->json([
                    'success' => false,
                    'already_stopped' => true,
                    'status' => 'stopped',
                    'message' => 'Pertandingan ini telah dihentikan. Penambahan poin ditolak.',
                    'winner_team' => null,
                    'stop_reason' => $stoppedReason,
                ], 422);
            }
        }

        // score_a / score_b are only required for snapshot-based saves (fallback).
        // When action=add_point with point_won_by provided, server computes the score.
        $actionField = $request->input('action', 'add_point');
        $hasEventAction = ($actionField === 'add_point' && $request->has('point_won_by')) ||
            ($actionField === 'completion') ||
            ($actionField === 'batch_events');

        // Normalisasi round dari match_key jika belum ada
        if (! $request->has('round') && $request->has('match_key')) {
            if (preg_match('/(round_\d+)/', (string) $request->input('match_key'), $rm)) {
                $request->merge(['round' => $rm[1]]);
            }
        }

        $request->validate([
            'game_id' => 'required|integer',
            'round' => 'required|string',
            'match_key' => 'nullable|string',
            'court' => 'nullable|integer',
            'score_a' => ($hasEventAction ? 'nullable' : 'required').'|integer|min:0',
            'score_b' => ($hasEventAction ? 'nullable' : 'required').'|integer|min:0',
            'point_display_a' => 'nullable|string',
            'point_display_b' => 'nullable|string',
            'set_number' => 'nullable|integer|min:1',
            'sets_a' => 'nullable|integer|min:0',
            'sets_b' => 'nullable|integer|min:0',
            'games_a' => 'nullable|integer|min:0',
            'games_b' => 'nullable|integer|min:0',
            'set_history' => 'nullable|array',
            'idx_a' => 'nullable|integer|min:0',
            'idx_b' => 'nullable|integer|min:0',
            'is_deuce' => 'nullable|boolean',
            'advantage' => 'nullable|string',
            'scoring_type' => 'nullable|string',
            'status' => 'nullable|string|in:in_progress,completed',
            'winner_team' => 'nullable|string',
            'client_id' => 'nullable|string',
            'client_seq' => 'nullable|integer',
            'event_id' => 'nullable|string',
            'action' => 'nullable|string',
            'point_won_by' => 'nullable|string|in:A,B',
            'base_version' => 'nullable|integer',
            'is_walkover' => 'nullable|boolean',
        ]);

        $round = $request->string('round')->toString();
        $matchKey = $request->input('match_key');
        if (! $matchKey) {
            $courtNum = max(1, $request->integer('court', 1));
            $courtCount = SessionModel::with('courts')->find($gameId)?->courts->count() ?? 1;
            $matchKey = ScoringService::buildMatchKey($round, $courtNum, max(1, $courtCount));
        }
        $status = $request->input('status', 'in_progress');
        $requestedCourt = $request->integer('court', 0);
        $courtIndex = $requestedCourt > 0 ? max(0, $requestedCourt - 1) : 0;
        $isWalkover = $request->boolean('is_walkover', false);

        // Simpan ke Cache (TTL 4 jam) agar bisa dibaca semua user
        $cacheKey = "scoring.game_{$gameId}";
        $scores = Cache::get($cacheKey, []);

        if (empty($scores[$matchKey])) {
            $dbScore = $this->getScoreFromDatabase($gameId, $round, $courtIndex);
            if ($dbScore && ($dbScore['status'] ?? '') === 'completed') {
                $scores[$matchKey] = $dbScore;
                Cache::put($cacheKey, $scores, now()->addHours(4));
            }
        }

        // Guard 1: Jika match dihentikan, tolak penambahan poin dengan status 422
        $mStatus = $scores[$matchKey]['status'] ?? '';
        if ($mStatus === 'stopped') {
            return response()->json([
                'success' => false,
                'status' => 'stopped',
                'message' => 'Pertandingan ini telah dihentikan. Penambahan poin ditolak.',
            ], 422);
        }

        // Jika ronde atau sesi sudah berstatus selesai, tolak pembaruan yang terlambat datang (late in-flight AJAX)
        if (($scores['_meta']['status'] ?? '') === 'finished' || $mStatus === 'completed') {
            return response()->json([
                'success' => true,
                'duplicate' => true,
                'already_completed' => true,
                'status' => $mStatus,
                'message' => 'Pertandingan sudah selesai. Pembaruan skor diabaikan.',
                'score_a' => (int) ($scores[$matchKey]['games_a'] ?? ($scores[$matchKey]['score_a'] ?? 0)),
                'score_b' => (int) ($scores[$matchKey]['games_b'] ?? ($scores[$matchKey]['score_b'] ?? 0)),
                'games_a' => (int) ($scores[$matchKey]['games_a'] ?? ($scores[$matchKey]['score_a'] ?? 0)),
                'games_b' => (int) ($scores[$matchKey]['games_b'] ?? ($scores[$matchKey]['score_b'] ?? 0)),
                'point_display_a' => (string) ($scores[$matchKey]['point_display_a'] ?? '0'),
                'point_display_b' => (string) ($scores[$matchKey]['point_display_b'] ?? '0'),
                'saved' => $scores[$matchKey] ?? [],
                'match_key' => $matchKey,
                'version' => (int) ($scores[$matchKey]['version'] ?? 0),
            ]);
        }

        $scoringSystemName = SessionModel::where('session_id', $gameId)->value('scoring_system')
            ?? 'Total of 3';
        $scoringSystem = ScoringService::detectScoringSystem($scoringSystemName);
        $gamesA = $request->integer('games_a', 0);
        $gamesB = $request->integer('games_b', 0);

        // Validasi ketidaksesuaian data: skor tidak boleh melebihi batas set Total Of
        if (($scoringSystem['is_total_of'] ?? false) && ($request->has('games_a') || $request->has('games_b'))) {
            if ($gamesA > 7 || $gamesB > 7 || ($gamesA === 7 && $gamesB < 5) || ($gamesB === 7 && $gamesA < 5)) {
                return response()->json([
                    'success' => false,
                    'error' => 'data_mismatch',
                    'message' => "Ketidaksesuaian data: skor ({$gamesA}–{$gamesB}) melebihi batas aturan set Total Of (maksimal 6 game menang, atau 7 jika 5–5).",
                ], 422);
            }
        }

        if ($status === 'completed') {
            if (! $isWalkover && ! ScoringService::isMatchCompleted($gamesA, $gamesB, $scoringSystem)) {
                return response()->json([
                    'success' => false,
                    'message' => "Pertandingan belum memenuhi syarat selesai untuk sistem skor {$scoringSystem['label']} (skor saat ini: {$gamesA}–{$gamesB}).",
                ], 422);
            }
        }

        // Fallback scoring system dari DB session (digunakan jika getGameData gagal)
        $scoringSystem = ScoringService::detectScoringSystem(
            SessionModel::where('session_id', $gameId)->value('scoring_system') ?? 'Total of 3'
        );

        try {
            $game = $this->getGameData($gameId);
            $scoringSystem = ScoringService::detectScoringSystem($game['scoring_system'] ?? 'Total of 3');
            $roundAccess = $this->getRoundAccess($game, $scoringSystem, $scores);
            if (! ($roundAccess[$round] ?? false)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ronde sebelumnya belum selesai.',
                ], 422);
            }
        } catch (\Throwable $e) {
            Log::warning('Unable to validate round access before score update: '.$e->getMessage());
        }

        // Guard 2: Cek apakah sesi di database sudah berstatus Finished
        try {
            $dbSessionStatus = SessionModel::where('session_id', $gameId)->value('status_session');
            if (strtolower($dbSessionStatus ?? '') === 'finished') {
                return response()->json([
                    'success' => false,
                    'message' => 'Sesi pertandingan sudah selesai di database. Pembaruan skor diabaikan.',
                    'saved' => $scores[$matchKey] ?? [],
                    'match_key' => $matchKey,
                ]);
            }
        } catch (\Throwable $e) {
            // Abaikan kegagalan koneksi DB sekunder pada update AJAX realtime
        }

        $courtIndex = 0;
        if (preg_match('/court_(\d+)/', $matchKey, $cm)) {
            $courtIndex = max(0, ((int) $cm[1]) - 1);
        } elseif ($request->has('court')) {
            $courtIndex = max(0, ((int) $request->input('court')) - 1);
        }

        // Serialize cache read/compare/write so concurrent requests cannot overwrite a newer snapshot.
        $tLockStart = microtime(true);
        $scoreLock = Cache::lock("scoring.update.{$gameId}.{$matchKey}", 10);
        $scoreLock->block(5);
        $tLockAcquired = microtime(true);
        $lockWaitMs = round(($tLockAcquired - $tLockStart) * 1000, 2);

        try {
            $scores = Cache::get($cacheKey, []);

            // Monotonic version per matchKey
            $prevVersion = (int) ($scores[$matchKey]['version'] ?? 0);
            $serverTimeMs = (int) round(microtime(true) * 1000);

            $clientId = (string) $request->input('client_id', '');
            $clientSeq = (int) $request->input('client_seq', $request->input('client_version', 0));
            $baseVersion = (int) $request->input('base_version', $prevVersion);
            $eventId = (string) $request->input('event_id', '');
            $action = (string) $request->input('action', 'add_point');
            $pointWonBy = $request->input('point_won_by'); // 'A' | 'B'
            $isCompletion = ($status === 'completed' || $action === 'completion');

            // 1. EVENT DEDUPLICATION (Requirement 2 & 3):
            // Cek apakah event_id yang sama sudah pernah diproses sebelumnya (dari cache atau DB persisten).
            $processedEvents = $scores[$matchKey]['processed_events'] ?? [];
            $lastSavedEventId = $scores[$matchKey]['last_event_id'] ?? '';
            $dbCheck = null;
            if (empty($lastSavedEventId) || empty($processedEvents) || $prevVersion === 0) {
                $dbCheck = $this->getScoreFromDatabase($gameId, $round, $courtIndex);
                if ($dbCheck) {
                    if (empty($lastSavedEventId)) {
                        $lastSavedEventId = $dbCheck['last_event_id'] ?? '';
                    }
                    if ($prevVersion === 0 && isset($dbCheck['version'])) {
                        $prevVersion = (int) $dbCheck['version'];
                    }
                }
            }

            $dbPersistentEventIds = $dbCheck['persistent_event_ids'] ?? [];
            if (! empty($dbPersistentEventIds)) {
                foreach ($dbPersistentEventIds as $pId) {
                    $processedEvents[$pId] = true;
                }
            }

            // Pengecekan duplikat berbasis IDENTITAS EVENT (bukan version semata):
            // Event diakui duplikat HANYA jika identitas event_id pernah diproses sebelumnya (di cache atau riwayat DB).
            // Jangan menganggap base_version < prevVersion sebagai duplikat, agar event sah dari host lain tetap di-merge.
            $isDuplicateEvent = (! empty($eventId) && (
                isset($processedEvents[$eventId])
                || ($lastSavedEventId !== '' && $eventId === $lastSavedEventId)
                || in_array($eventId, $dbPersistentEventIds, true)
            ));

            if (! empty($eventId) && $action !== 'batch_events' && $isDuplicateEvent) {
                $savedState = ! empty($scores[$matchKey]) ? $scores[$matchKey] : ($dbCheck ?? []);

                return response()->json([
                    'success' => true,
                    'duplicate' => true,
                    'message' => 'Event duplicate ignored.',
                    'score_a' => (int) ($savedState['games_a'] ?? ($savedState['score_a'] ?? 0)),
                    'score_b' => (int) ($savedState['games_b'] ?? ($savedState['score_b'] ?? 0)),
                    'games_a' => (int) ($savedState['games_a'] ?? ($savedState['score_a'] ?? 0)),
                    'games_b' => (int) ($savedState['games_b'] ?? ($savedState['score_b'] ?? 0)),
                    'point_display_a' => (string) ($savedState['point_display_a'] ?? '0'),
                    'point_display_b' => (string) ($savedState['point_display_b'] ?? '0'),
                    'saved' => $savedState,
                    'match_key' => $matchKey,
                    'version' => $prevVersion,
                    'server_version' => $prevVersion,
                    'last_event_id' => $eventId,
                    'event_id' => $eventId,
                    'server_time_ms' => $serverTimeMs,
                    'client_seq' => $clientSeq,
                    'stale_ignored' => false,
                ])->header('Cache-Control', 'no-cache, no-store, must-revalidate');
            }

            // 2. PER-CLIENT SEQUENCE CHECK (Requirement 1 & 4):
            // Menolak request out-of-order hanya jika berasal dari client_id yang sama.
            $prevClientSeq = (int) ($scores[$matchKey]['clients'][$clientId] ?? 0);
            if (! empty($clientId) && $clientSeq > 0 && $prevClientSeq > 0 && $clientSeq < $prevClientSeq) {
                return response()->json([
                    'success' => true,
                    'message' => 'Stale update ignored.',
                    'score_a' => (int) ($scores[$matchKey]['games_a'] ?? ($scores[$matchKey]['score_a'] ?? 0)),
                    'score_b' => (int) ($scores[$matchKey]['games_b'] ?? ($scores[$matchKey]['score_b'] ?? 0)),
                    'games_a' => (int) ($scores[$matchKey]['games_a'] ?? ($scores[$matchKey]['score_a'] ?? 0)),
                    'games_b' => (int) ($scores[$matchKey]['games_b'] ?? ($scores[$matchKey]['score_b'] ?? 0)),
                    'point_display_a' => (string) ($scores[$matchKey]['point_display_a'] ?? '0'),
                    'point_display_b' => (string) ($scores[$matchKey]['point_display_b'] ?? '0'),
                    'saved' => $scores[$matchKey],
                    'match_key' => $matchKey,
                    'version' => $prevVersion,
                    'server_version' => $prevVersion,
                    'last_event_id' => $eventId,
                    'event_id' => $eventId,
                    'server_time_ms' => $serverTimeMs,
                    'client_seq' => $clientSeq,
                    'stale_ignored' => true,
                ])->header('Cache-Control', 'no-cache, no-store, must-revalidate');
            }

            // 3. EVENT-BASED SCORING CALCULATION (Requirement 1, 2, 3):
            // Otoritas skor dihitung oleh server berdasarkan scoring event (bukan snapshot client).
            $currentState = $scores[$matchKey] ?? [];
            if (empty($currentState)) {
                $dbScore = $this->getScoreFromDatabase($gameId, $round, $courtIndex);
                $currentState = $dbScore ?: [
                    'score_a' => 0,
                    'score_b' => 0,
                    'games_a' => 0,
                    'games_b' => 0,
                    'point_display_a' => '0',
                    'point_display_b' => '0',
                    'set_number' => 1,
                    'sets_a' => 0,
                    'sets_b' => 0,
                    'set_history' => [],
                    'idx_a' => 0,
                    'idx_b' => 0,
                    'is_deuce' => false,
                    'advantage' => null,
                    'scoring_type' => $scoringSystem['type'] ?? 'total_of_sets',
                    'status' => 'in_progress',
                    'winner_team' => null,
                    'version' => $prevVersion,
                ];
            }

            // Final resmi bersifat monotonic: snapshot stale dari client lama tidak boleh menurunkan
            // skor yang sudah selesai di cache atau database. Jaga skor 6-4 tetap 6-4 meski request lama 5-4 datang.
            $officialCompletedState = $currentState;
            if (! ScoringService::isMatchTerminal($officialCompletedState['status'] ?? '')) {
                $dbScore = $this->getScoreFromDatabase($gameId, $round, $courtIndex);
                if ($dbScore && ScoringService::isMatchTerminal($dbScore['status'] ?? '')) {
                    $officialCompletedState = $dbScore;
                }
            }

            if (! ScoringService::isMatchTerminal($officialCompletedState['status'] ?? '')) {
                $rawFinalScore = $this->getOfficialCompletedScoreFromDatabase($gameId, $round, $courtIndex);
                if ($rawFinalScore) {
                    $officialCompletedState = $rawFinalScore;
                }
            }

            if (($currentState['status'] ?? '') === 'stopped' || ($officialCompletedState['status'] ?? '') === 'stopped') {
                $stoppedSource = (($officialCompletedState['status'] ?? '') === 'stopped') ? $officialCompletedState : $currentState;

                return response()->json([
                    'success' => false,
                    'already_stopped' => true,
                    'status' => 'stopped',
                    'message' => 'Pertandingan telah dihentikan. Penambahan skor ditolak.',
                    'score_a' => (int) ($stoppedSource['games_a'] ?? ($stoppedSource['score_a'] ?? 0)),
                    'score_b' => (int) ($stoppedSource['games_b'] ?? ($stoppedSource['score_b'] ?? 0)),
                    'games_a' => (int) ($stoppedSource['games_a'] ?? ($stoppedSource['score_a'] ?? 0)),
                    'games_b' => (int) ($stoppedSource['games_b'] ?? ($stoppedSource['score_b'] ?? 0)),
                    'stop_type' => $stoppedSource['stop_type'] ?? null,
                    'stop_reason' => $stoppedSource['stop_reason'] ?? null,
                    'stopped_at' => $stoppedSource['stopped_at'] ?? null,
                    'winner_team' => null,
                ], 422);
            }

            $incomingGamesA = (int) $request->input('games_a', $currentState['games_a'] ?? ($currentState['score_a'] ?? 0));
            $incomingGamesB = (int) $request->input('games_b', $currentState['games_b'] ?? ($currentState['score_b'] ?? 0));
            $officialGamesA = (int) ($officialCompletedState['games_a'] ?? ($officialCompletedState['score_a'] ?? 0));
            $officialGamesB = (int) ($officialCompletedState['games_b'] ?? ($officialCompletedState['score_b'] ?? 0));
            $currentOfficialCompleted = (($officialCompletedState['status'] ?? '') === 'completed')
                || (($scores['_meta']['status'] ?? '') === 'finished')
                || (($scores[$matchKey]['status'] ?? '') === 'completed');
            $staleCompletionWouldDowngrade = $currentOfficialCompleted && (
                ($incomingGamesA + $incomingGamesB) < ($officialGamesA + $officialGamesB)
                || ($incomingGamesA < $officialGamesA && $incomingGamesB <= $officialGamesB)
                || ($incomingGamesB < $officialGamesB && $incomingGamesA <= $officialGamesA)
            );
            if ($staleCompletionWouldDowngrade) {
                return response()->json([
                    'success' => true,
                    'duplicate' => true,
                    'already_completed' => true,
                    'message' => 'Skor final sudah resmi tersimpan. Pembaruan stale diabaikan.',
                    'score_a' => $officialGamesA,
                    'score_b' => $officialGamesB,
                    'games_a' => $officialGamesA,
                    'games_b' => $officialGamesB,
                    'point_display_a' => (string) ($officialCompletedState['point_display_a'] ?? '0'),
                    'point_display_b' => (string) ($officialCompletedState['point_display_b'] ?? '0'),
                    'saved' => $officialCompletedState,
                    'match_key' => $matchKey,
                    'version' => (int) ($officialCompletedState['version'] ?? $prevVersion),
                    'server_version' => (int) ($officialCompletedState['version'] ?? $prevVersion),
                    'status' => $officialCompletedState['status'] ?? 'completed',
                    'winner_team' => $officialCompletedState['winner_team'] ?? null,
                ]);
            }

            $isMerged = false;
            $appliedBatchEventIds = [];

            if ($action === 'batch_events') {
                $batchEvents = $request->input('events', []);
                $scorePayload = $currentState;

                // Cek apakah ada event di batch yang sudah pernah tersimpan di DB (last_event_id persisten)
                $indexOfLastSavedEvent = -1;
                if (! empty($lastSavedEventId)) {
                    foreach ($batchEvents as $idx => $ev) {
                        if (($ev['event_id'] ?? '') === $lastSavedEventId) {
                            $indexOfLastSavedEvent = $idx;
                            break;
                        }
                    }
                }

                // Cek apakah ada minimal satu event yang belum pernah diproses
                $hasAnyUnprocessedEvent = false;
                foreach ($batchEvents as $idx => $ev) {
                    $evId = $ev['event_id'] ?? '';
                    if ($indexOfLastSavedEvent !== -1 && $idx <= $indexOfLastSavedEvent) {
                        continue;
                    }
                    if (! empty($evId) && (isset($processedEvents[$evId]) || in_array($evId, $dbPersistentEventIds, true))) {
                        continue;
                    }
                    $hasAnyUnprocessedEvent = true;
                    break;
                }

                if (! $hasAnyUnprocessedEvent) {
                    return response()->json([
                        'success' => true,
                        'duplicate' => true,
                        'message' => 'Batch events already processed.',
                        'score_a' => (int) ($currentState['games_a'] ?? ($currentState['score_a'] ?? 0)),
                        'score_b' => (int) ($currentState['games_b'] ?? ($currentState['score_b'] ?? 0)),
                        'games_a' => (int) ($currentState['games_a'] ?? ($currentState['score_a'] ?? 0)),
                        'games_b' => (int) ($currentState['games_b'] ?? ($currentState['score_b'] ?? 0)),
                        'point_display_a' => (string) ($currentState['point_display_a'] ?? '0'),
                        'point_display_b' => (string) ($currentState['point_display_b'] ?? '0'),
                        'saved' => $currentState,
                        'match_key' => $matchKey,
                        'version' => $prevVersion,
                        'server_version' => $prevVersion,
                        'last_event_id' => $lastSavedEventId,
                        'event_id' => $eventId,
                        'server_time_ms' => $serverTimeMs,
                        'client_seq' => $clientSeq,
                        'stale_ignored' => true,
                    ])->header('Cache-Control', 'no-cache, no-store, must-revalidate');
                }

                foreach ($batchEvents as $idx => $ev) {
                    $evAction = $ev['action'] ?? 'add_point';
                    $evEventId = $ev['event_id'] ?? '';
                    $evTeam = $ev['team'] ?? '';

                    // 1. Lewati event yang sudah ada di index sebelum atau pada last_event_id yang tersimpan di DB
                    if ($indexOfLastSavedEvent !== -1 && $idx <= $indexOfLastSavedEvent) {
                        continue;
                    }

                    // 2. Lewati event yang sudah tercatat di processed_events cache atau persistent DB
                    if (! empty($evEventId) && (isset($processedEvents[$evEventId]) || in_array($evEventId, $dbPersistentEventIds, true))) {
                        continue;
                    }

                    if ($evAction === 'add_point' && ! empty($evTeam) && in_array($evTeam, ['A', 'B'])) {
                        if (($scorePayload['status'] ?? '') !== 'completed') {
                            $scorePayload = $this->applyPointDeltaToState($scorePayload, $evTeam, $scoringSystem);
                            if (! empty($evEventId)) {
                                $appliedBatchEventIds[] = $evEventId;
                            }
                            if (($scorePayload['status'] ?? '') === 'completed') {
                                break;
                            }
                        }
                    } elseif ($evAction === 'completion' || ($ev['status'] ?? '') === 'completed') {
                        $evGamesA = isset($ev['games_a']) ? (int) $ev['games_a'] : (isset($ev['snapshot']['gamesA']) ? (int) $ev['snapshot']['gamesA'] : null);
                        $evGamesB = isset($ev['games_b']) ? (int) $ev['games_b'] : (isset($ev['snapshot']['gamesB']) ? (int) $ev['snapshot']['gamesB'] : null);
                        $scorePayload = $this->applyCompletionToState($scorePayload, $scoringSystem, $ev['team'] ?? null, $evGamesA, $evGamesB);
                        if (! empty($evEventId)) {
                            $appliedBatchEventIds[] = $evEventId;
                        }
                    }

                    if (! empty($evEventId)) {
                        $processedEvents[$evEventId] = true;
                    }
                }
                if ($isCompletion) {
                    $finalGamesA = $request->has('games_a')
                        ? $request->integer('games_a')
                        : null;

                    $finalGamesB = $request->has('games_b')
                        ? $request->integer('games_b')
                        : null;

                    if ($finalGamesA !== null && $finalGamesB !== null) {
                        if (ScoringService::isMatchCompleted($finalGamesA, $finalGamesB, $scoringSystem)) {
                            $scorePayload['games_a'] = $finalGamesA;
                            $scorePayload['games_b'] = $finalGamesB;
                            $scorePayload['score_a'] = $finalGamesA;
                            $scorePayload['score_b'] = $finalGamesB;
                            $scorePayload['status'] = 'completed';

                            $winner = ScoringService::determineMatchWinner($finalGamesA, $finalGamesB, $scoringSystem);
                            $scorePayload['winner_team'] = $winner;
                            $scorePayload['sets_a'] = ($winner === 'Team A') ? 1 : 0;
                            $scorePayload['sets_b'] = ($winner === 'Team B') ? 1 : 0;
                        }
                    }
                }

                $isMerged = ($baseVersion < $prevVersion);
            } elseif ($action === 'add_point' && ! empty($pointWonBy) && in_array($pointWonBy, ['A', 'B']) && ($currentState['status'] ?? '') !== 'completed') {
                $scorePayload = $this->applyPointDeltaToState($currentState, $pointWonBy, $scoringSystem);
                $isMerged = ($baseVersion < $prevVersion);
            } elseif ($isCompletion) {
                $incGamesA = $request->has('games_a') ? $request->integer('games_a') : null;
                $incGamesB = $request->has('games_b') ? $request->integer('games_b') : null;
                $scorePayload = $this->applyCompletionToState($currentState, $scoringSystem, $request->input('winner_team'), $incGamesA, $incGamesB);
            } else {
                // Fallback snapshot hanya jika action/point_won_by tidak tersedia
                $scorePayload = [
                    'score_a' => $request->integer('score_a', $currentState['games_a'] ?? 0),
                    'score_b' => $request->integer('score_b', $currentState['games_b'] ?? 0),
                    'point_display_a' => (string) $request->input('point_display_a', $currentState['point_display_a'] ?? '0'),
                    'point_display_b' => (string) $request->input('point_display_b', $currentState['point_display_b'] ?? '0'),
                    'set_number' => $request->integer('set_number', $currentState['set_number'] ?? 1),
                    'sets_a' => $request->integer('sets_a', $currentState['sets_a'] ?? 0),
                    'sets_b' => $request->integer('sets_b', $currentState['sets_b'] ?? 0),
                    'games_a' => $request->integer('games_a', $currentState['games_a'] ?? 0),
                    'games_b' => $request->integer('games_b', $currentState['games_b'] ?? 0),
                    'set_history' => $request->input('set_history', $currentState['set_history'] ?? []),
                    'idx_a' => $request->integer('idx_a', $currentState['idx_a'] ?? 0),
                    'idx_b' => $request->integer('idx_b', $currentState['idx_b'] ?? 0),
                    'is_deuce' => (bool) $request->input('is_deuce', $currentState['is_deuce'] ?? false),
                    'advantage' => $request->input('advantage', $currentState['advantage'] ?? null),
                    'scoring_type' => $request->input('scoring_type', $currentState['scoring_type'] ?? 'total_of_sets'),
                    'status' => $status,
                    'winner_team' => $request->input('winner_team', $currentState['winner_team'] ?? null),
                ];
            }

            $newVersion = $prevVersion + 1;

            // Pertahankan map client sequences
            $clientMap = $currentState['clients'] ?? [];
            if (! empty($clientId)) {
                $clientMap[$clientId] = max($clientSeq, $prevClientSeq);
            }

            // Catat event_id yang telah diproses (batasi max 100 terakhir di cache)
            if (! empty($eventId)) {
                $processedEvents[$eventId] = [
                    'ver' => $newVersion,
                    'by' => $clientId,
                    'time' => $serverTimeMs,
                ];
                if (count($processedEvents) > 100) {
                    $processedEvents = array_slice($processedEvents, -100, 100, true);
                }
            }

            $scorePayload['version'] = $newVersion;
            $scorePayload['server_version'] = $newVersion;
            $scorePayload['last_event_id'] = $eventId;
            $scorePayload['updated_at_ms'] = $serverTimeMs;
            $scorePayload['clients'] = $clientMap;
            $scorePayload['processed_events'] = $processedEvents;
            $scorePayload['updated_at'] = now()->toDateTimeString();

            $isMultiCourt = str_contains($matchKey, '_court_');

            // 1. Persistensi ke Database (tb_match, tb_match_participant, tb_score) secara ATOMIC terlebih dahulu
            try {
                DB::transaction(function () use ($gameId, $round, $matchKey, $scorePayload, $newVersion, $eventId, $request, $action, $appliedBatchEventIds) {
                    $session = SessionModel::with(['players', 'courts'])->find($gameId);
                    if ($session) {
                        $courtIndex = 0;
                        if (preg_match('/court_(\d+)/', $matchKey, $cm)) {
                            $courtIndex = max(0, ((int) $cm[1]) - 1);
                        } elseif ($request->has('court')) {
                            $courtIndex = max(0, $request->integer('court') - 1);
                        }

                        // Reuse atau create match & participants
                        $match = ScoringService::ensureMatchAndParticipants($session, $round, $courtIndex);
                        if ($match) {
                            $finalStatus = $scorePayload['status'] ?? 'in_progress';
                            $matchSummary = "Game Score {$scorePayload['games_a']} - {$scorePayload['games_b']}";
                            $match->status_match = ($finalStatus === 'completed') ? 'Completed' : 'In Progress';
                            $match->hasil_pertandingan = $matchSummary;
                            $match->version = $newVersion;
                            $match->last_event_id = $eventId;
                            if ($finalStatus === 'completed' && ! empty($scorePayload['winner_team'])) {
                                $match->winner_team = $scorePayload['winner_team'];
                                $match->waktu_selesai = now();
                            }
                            $match->save();

                            // 1. Simpan / update baris aktif set di tb_score (agar score_id dan baris set terupdate)
                            $setNumber = $scorePayload['set_number'] ?? 1;
                            Score::updateOrCreate(
                                [
                                    'match_id' => $match->match_id,
                                    'set_number' => $setNumber,
                                ],
                                [
                                    'game_number' => 1,
                                    'point_score_a' => (string) ($scorePayload['point_display_a'] ?? '0'),
                                    'point_score_b' => (string) ($scorePayload['point_display_b'] ?? '0'),
                                    'game_score_a' => (int) ($scorePayload['games_a'] ?? 0),
                                    'game_score_b' => (int) ($scorePayload['games_b'] ?? 0),
                                    'set_score_a' => (int) ($scorePayload['sets_a'] ?? 0),
                                    'set_score_b' => (int) ($scorePayload['sets_b'] ?? 0),
                                    'score_side_a' => (int) ($scorePayload['games_a'] ?? 0),
                                    'score_side_b' => (int) ($scorePayload['games_b'] ?? 0),
                                    'scoring_system' => (string) ($scorePayload['scoring_type'] ?? 'total_of_sets'),
                                    'status_score' => ($finalStatus === 'completed') ? 'Final' : 'In Progress',
                                    'version' => $newVersion,
                                    'last_event_id' => $eventId,
                                ]
                            );

                            // 2. Simpan entri event log agar identitas event_id persisten tidak hilang saat cache dingin
                            if (! empty($eventId)) {
                                Score::create([
                                    'match_id' => $match->match_id,
                                    'set_number' => $setNumber,
                                    'game_number' => $newVersion + 1000,
                                    'point_score_a' => (string) ($scorePayload['point_display_a'] ?? '0'),
                                    'point_score_b' => (string) ($scorePayload['point_display_b'] ?? '0'),
                                    'game_score_a' => (int) ($scorePayload['games_a'] ?? 0),
                                    'game_score_b' => (int) ($scorePayload['games_b'] ?? 0),
                                    'set_score_a' => (int) ($scorePayload['sets_a'] ?? 0),
                                    'set_score_b' => (int) ($scorePayload['sets_b'] ?? 0),
                                    'score_side_a' => (int) ($scorePayload['games_a'] ?? 0),
                                    'score_side_b' => (int) ($scorePayload['games_b'] ?? 0),
                                    'scoring_system' => (string) ($scorePayload['scoring_type'] ?? 'total_of_sets'),
                                    'status_score' => 'EventLog',
                                    'version' => $newVersion,
                                    'last_event_id' => $eventId,
                                ]);
                            }

                            if ($action === 'batch_events' && ! empty($appliedBatchEventIds)) {
                                foreach ($appliedBatchEventIds as $bevId) {
                                    if ($bevId !== $eventId) {
                                        Score::create([
                                            'match_id' => $match->match_id,
                                            'set_number' => $setNumber,
                                            'game_number' => $newVersion + 1000,
                                            'point_score_a' => (string) ($scorePayload['point_display_a'] ?? '0'),
                                            'point_score_b' => (string) ($scorePayload['point_display_b'] ?? '0'),
                                            'game_score_a' => (int) ($scorePayload['games_a'] ?? 0),
                                            'game_score_b' => (int) ($scorePayload['games_b'] ?? 0),
                                            'set_score_a' => (int) ($scorePayload['sets_a'] ?? 0),
                                            'set_score_b' => (int) ($scorePayload['sets_b'] ?? 0),
                                            'score_side_a' => (int) ($scorePayload['games_a'] ?? 0),
                                            'score_side_b' => (int) ($scorePayload['games_b'] ?? 0),
                                            'scoring_system' => (string) ($scorePayload['scoring_type'] ?? 'total_of_sets'),
                                            'status_score' => 'EventLog',
                                            'version' => $newVersion,
                                            'last_event_id' => $bevId,
                                        ]);
                                    }
                                }
                            }

                        }
                    }
                });
                $tDbDone = microtime(true);
                $dbMs = round(($tDbDone - $tLockAcquired) * 1000, 2);
            } catch (\Throwable $e) {
                Log::error('Failed to persist live score to database: '.$e->getMessage());

                return response()->json([
                    'success' => false,
                    'message' => 'Gagal menyimpan skor ke basis data. Silakan coba kembali.',
                ], 500);
            }

            // 2. Simpan ke Cache hanya setelah DB commit berhasil (dilindungi sessionLock agar multi-court tidak saling menimpa)
            $sessionLock = Cache::lock("scoring.session.{$gameId}", 5);
            $sessionLock->block(3);
            try {
                $scores = Cache::get($cacheKey, []);
                $scores[$matchKey] = $scorePayload;
                if (! $isMultiCourt) {
                    $scores[$round] = $scorePayload;
                    $scores["{$round}_court_1"] = $scorePayload;
                }
                Cache::put($cacheKey, $scores, now()->addHours(4));
                Cache::put("scoring.game_{$gameId}.{$matchKey}", $scorePayload, now()->addHours(4));

                // Jika format Single 4 pemain 1 court, update cache schedule dengan pasangan dinamis
                if (($scorePayload['status'] ?? '') === 'completed') {
                    $cachedScheduleKey = "drawing_schedule_{$gameId}";
                    $cachedSchedule = Cache::get($cachedScheduleKey);
                    if ($cachedSchedule && ! empty($cachedSchedule['rounds'])) {
                        $americanoService = new AmericanoService;
                        $updatedRounds = $americanoService->resolveDynamicSingleRounds($cachedSchedule['rounds'], $scores, null, (int) $gameId);
                        $cachedSchedule['rounds'] = $updatedRounds;
                        if (isset($cachedSchedule['drawingData'])) {
                            $cachedSchedule['drawingData']['rounds'] = $updatedRounds;
                        }
                        Cache::put($cachedScheduleKey, $cachedSchedule, now()->addHours(12));
                    }
                }
            } finally {
                $sessionLock->release();
            }
            $tCacheDone = microtime(true);
            $cacheMs = round(($tCacheDone - $tDbDone) * 1000, 2);
        } finally {
            // 3. Lepas scoreLock SEGERA setelah persistensi (DB + Cache) selesai
            $scoreLock->release();
        }

        $totalMs = round((microtime(true) - $tStart) * 1000, 2);

        // 4. Broadcast Realtime dijalankan DI LUAR LOCK secara non-blocking agar tidak menahan response client
        $broadcastTask = function () use ($gameId, $matchKey, $scorePayload) {
            $this->broadcastScoreUpdateRealtime($gameId, $matchKey, $scorePayload);
        };

        if (app()->runningUnitTests()) {
            $broadcastTask();
        } else {
            app()->terminating($broadcastTask);
            if (function_exists('fastcgi_finish_request')) {
                register_shutdown_function('fastcgi_finish_request');
            }
        }

        return response()->json([
            'success' => true,
            'merged' => $isMerged,
            'message' => $isMerged ? 'Skor berhasil diselaraskan dengan host lain.' : 'Skor berhasil disimpan.',
            'score_a' => (int) ($scorePayload['games_a'] ?? 0),
            'score_b' => (int) ($scorePayload['games_b'] ?? 0),
            'games_a' => (int) ($scorePayload['games_a'] ?? 0),
            'games_b' => (int) ($scorePayload['games_b'] ?? 0),
            'point_display_a' => (string) ($scorePayload['point_display_a'] ?? '0'),
            'point_display_b' => (string) ($scorePayload['point_display_b'] ?? '0'),
            'sets_a' => (int) ($scorePayload['sets_a'] ?? 0),
            'sets_b' => (int) ($scorePayload['sets_b'] ?? 0),
            'server_version' => $newVersion,
            'version' => $newVersion,
            'last_event_id' => $eventId,
            'event_id' => $eventId,
            'status' => $scorePayload['status'] ?? 'in_progress',
            'winner_team' => $scorePayload['winner_team'] ?? null,
            'saved' => $scores[$matchKey],
            'match_key' => $matchKey,
            'server_time_ms' => $serverTimeMs,
            'client_seq' => $clientSeq,
            'stale_ignored' => false,
            '_timings' => [
                'lock_wait_ms' => $lockWaitMs ?? 0,
                'db_ms' => $dbMs ?? 0,
                'cache_ms' => $cacheMs ?? 0,
                'total_ms' => $totalMs,
            ],
        ])->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('X-Correlation-Id', (string) ($eventId ?: Str::uuid()))
            ->header('X-Scoring-Time-Ms', (string) $totalMs);
    }

    /**
     * Akhiri Pertandingan (End Match) pada match/court tertentu yang sedang berlangsung.
     * Khusus Host/Pengelola yang berwenang.
     * Mengakhiri match yang dipilih tanpa otomatis mengakhiri court lain, ronde, atau seluruh sesi.
     */
    public function stopMatch(Request $request)
    {
        if (! Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Silakan login terlebih dahulu.',
            ], 403);
        }

        $gameId = (int) ($request->input('game_id') ?: $request->input('session_id'));
        if (! $gameId) {
            return response()->json([
                'success' => false,
                'message' => 'ID Sesi diperlukan.',
            ], 422);
        }

        $session = SessionModel::with(['players.user', 'courts', 'sport', 'host'])->find($gameId);
        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi tidak ditemukan.',
            ], 404);
        }

        if (! $this->isHostForSession($session)) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Hanya Host yang dapat mengakhiri pertandingan.',
            ], 403);
        }

        // Normalisasi alias input sebelum validasi
        if (! $request->has('round') && $request->has('round_key')) {
            $request->merge(['round' => $request->input('round_key')]);
        }
        if (! $request->has('court') && $request->has('court_number')) {
            $request->merge(['court' => $request->input('court_number')]);
        }
        if (! $request->has('reason') && $request->has('stop_reason')) {
            $request->merge(['reason' => $request->input('stop_reason')]);
        }
        if (! $request->has('player_reasons') && $request->has('player_notes')) {
            $request->merge(['player_reasons' => $request->input('player_notes')]);
        }

        $rawReason = trim((string) $request->input('reason', ''));
        if (empty($rawReason)) {
            $pNotes = $request->input('player_reasons', $request->input('player_notes', []));
            if (! empty($pNotes) && is_array($pNotes)) {
                $joined = implode('; ', array_filter($pNotes));
                if (! empty($joined)) {
                    $request->merge(['reason' => 'Pemain keluar: '.$joined]);
                }
            }
        }
        $rawStopType = $request->input('stop_type');
        if (! $request->has('reason') || empty(trim((string) $request->input('reason', '')))) {
            if (in_array($rawStopType, ['withdraw', 'withdraw_players'], true)) {
                $request->merge(['reason' => 'Pemain keluar dari sesi pertandingan']);
            } elseif (in_array($rawStopType, ['temporary_pause', 'pause_players'], true)) {
                $request->merge(['reason' => 'Istirahat sementara (masuk bangku cadangan)']);
            }
        }

        $request->validate([
            'game_id' => 'required|integer',
            'round' => 'required|string',
            'court' => 'nullable|integer',
            'match_key' => 'nullable|string',
            'stop_type' => 'required|in:keep_active,withdraw,keep_in_session,withdraw_players,temporary_pause,pause_players',
            'reason' => 'required|string|min:3',
            'withdrawn_player_ids' => 'nullable|array',
            'player_reasons' => 'nullable|array',
            'affected_player_id' => 'nullable|integer',
        ]);

        $round = $request->string('round')->toString();
        $courtIndex = max(0, $request->integer('court', 1) - 1);
        $courtCount = max(1, $session->courts->count());
        $matchKey = $request->input('match_key') ?: ScoringService::buildMatchKey($round, $courtIndex + 1, $courtCount);

        $stopType = (string) $request->input('stop_type');
        $isWithdraw = in_array($stopType, ['withdraw', 'withdraw_players'], true);
        $isPause = in_array($stopType, ['temporary_pause', 'pause_players'], true);
        $reason = trim((string) $request->input('reason', $request->input('stop_reason', '')));
        if ($isPause && empty($reason)) {
            $reason = 'Istirahat sementara (masuk bangku cadangan)';
        }

        $rawWithdrawn = $request->input('withdrawn_player_ids', $request->input('withdrawn_players', []));
        if (! is_array($rawWithdrawn)) {
            $rawWithdrawn = [$rawWithdrawn];
        }
        $rawWithdrawn = array_values(array_filter($rawWithdrawn, fn ($v) => $v !== null && trim((string) $v) !== ''));

        $rawReasons = $request->input('player_reasons', $request->input('player_notes', []));
        if (! is_array($rawReasons)) {
            $rawReasons = [];
        }

        if (($isWithdraw || $isPause) && empty($rawWithdrawn)) {
            return response()->json([
                'success' => false,
                'message' => $isPause ? 'Pilih minimal satu pemain yang istirahat sementara.' : 'Pilih minimal satu pemain yang keluar dari sesi.',
            ], 422);
        }

        // Resolusi pemain sesi dan peserta match terkait
        $sessionPlayers = $session->players;
        $sessionPlayerMapById = $sessionPlayers->keyBy('player_id');
        $sessionPlayerMapByName = $sessionPlayers->keyBy(fn ($p) => ScoringService::cleanPlayerName($p->nama));

        $gameData = $this->getGameData($gameId);
        $matchContext = ScoringService::buildMatchContext($gameData, $round, $courtIndex);
        $targetMatch = $matchContext['matches'][$courtIndex] ?? ($matchContext['matches'][0] ?? null);
        $matchParticipantCleanNames = [];
        if ($targetMatch) {
            $matchParticipantCleanNames = array_map(
                fn ($n) => ScoringService::cleanPlayerName(is_array($n) ? ($n['name'] ?? $n['nama'] ?? '') : (string) $n),
                array_merge($targetMatch['team_a_names'] ?? [], $targetMatch['team_b_names'] ?? [])
            );
        }

        $withdrawnPlayerIds = [];
        $withdrawnPlayerNames = [];
        $playerReasons = [];

        foreach ($rawWithdrawn as $item) {
            $itemStr = trim((string) $item);
            $cleanName = ScoringService::cleanPlayerName($itemStr);
            $foundPlayer = null;

            if (is_numeric($itemStr) && (int) $itemStr > 0) {
                $foundPlayer = $sessionPlayerMapById->get((int) $itemStr);
            }
            if (! $foundPlayer) {
                $foundPlayer = $sessionPlayerMapByName->get($cleanName);
            }
            if (! $foundPlayer && is_numeric($itemStr) && (int) $itemStr > 0) {
                $foundPlayer = Player::find((int) $itemStr);
            }
            if (! $foundPlayer) {
                $foundPlayer = Player::where('nama', $itemStr)->first();
            }

            if ($foundPlayer) {
                $pId = (int) $foundPlayer->player_id;
                $pName = (string) $foundPlayer->nama;
            } else {
                $pId = is_numeric($itemStr) && (int) $itemStr > 0 ? (int) $itemStr : null;
                $pName = $itemStr;
            }

            // Validasi peserta: pastikan pemain merupakan peserta match ini jika match participants terdefinisi
            if (! empty($matchParticipantCleanNames)) {
                $isMatchPart = in_array(ScoringService::cleanPlayerName($pName), $matchParticipantCleanNames, true);
                if (! $isMatchPart && $foundPlayer) {
                    $isMatchPart = in_array(ScoringService::cleanPlayerName($foundPlayer->nama), $matchParticipantCleanNames, true);
                }
                if (! $isMatchPart) {
                    return response()->json([
                        'success' => false,
                        'message' => "Pemain {$pName} bukan merupakan peserta pada pertandingan ini.",
                    ], 422);
                }
            }

            $note = trim((string) ($rawReasons[$item] ?? ($rawReasons[$itemStr] ?? ($rawReasons[$pId] ?? ($rawReasons[$pName] ?? '')))));
            if ($note === '' && ! empty($rawReason) && ! in_array($rawReason, ['Pemain keluar dari sesi pertandingan', 'Istirahat sementara (masuk bangku cadangan)'], true)) {
                $note = $rawReason;
            }
            if ($isPause && $note === '') {
                $note = $reason ?: 'Istirahat sementara (masuk bangku cadangan)';
            }

            // Validasi alasan per pemain keluar wajib diisi untuk withdrawal permanen
            if ($isWithdraw && $note === '') {
                return response()->json([
                    'success' => false,
                    'message' => "Wajib mencatat alasan/kendala untuk {$pName}.",
                ], 422);
            }

            if ($pId !== null) {
                $withdrawnPlayerIds[] = $pId;
                $withdrawnPlayerNames[$pId] = $pName;
                $playerReasons[$pId] = $note;
            } else {
                $withdrawnPlayerNames[$pName] = $pName;
                $playerReasons[$pName] = $note;
            }
        }

        $withdrawnPlayerIds = array_values(array_unique($withdrawnPlayerIds));

        if (($isWithdraw || $isPause) && empty($withdrawnPlayerIds) && empty($withdrawnPlayerNames)) {
            return response()->json([
                'success' => false,
                'message' => $isPause ? 'Pilih minimal satu pemain yang istirahat sementara.' : 'Pilih minimal satu pemain yang keluar dari sesi.',
            ], 422);
        }

        $correlationId = (string) Str::uuid();

        // Penguncian kompatibel dengan updateScore
        $scoreLock = Cache::lock("scoring.lock.{$gameId}.{$matchKey}", 5);
        $scoreLock->block(3);
        try {
            $cacheKey = "scoring.game_{$gameId}";
            $scores = Cache::get($cacheKey, []);
            $currentState = $scores[$matchKey] ?? ($courtCount === 1 ? ($scores[$round] ?? null) : null);
            if (empty($currentState)) {
                $currentState = Cache::get("scoring.game_{$gameId}.{$matchKey}");
            }
            if (empty($currentState)) {
                $game = $this->getGameData($gameId);
                $scoringSystem = ScoringService::detectScoringSystem($game['scoring_system'] ?? '');
                $currentState = $this->getDefaultMatchState($game, $matchKey, $round, $scoringSystem);
            }

            // Jika ada skor parsial yang dikirim dalam request penghentian, pertahankan skor tersebut
            $incGamesA = $request->input('current_games_a', $request->input('games_a'));
            $incGamesB = $request->input('current_games_b', $request->input('games_b'));
            if ($incGamesA !== null) {
                $currentState['games_a'] = (int) $incGamesA;
                $currentState['score_a'] = (int) $incGamesA;
            }
            if ($incGamesB !== null) {
                $currentState['games_b'] = (int) $incGamesB;
                $currentState['score_b'] = (int) $incGamesB;
            }
            $incPtA = $request->input('point_a', $request->input('point_display_a'));
            $incPtB = $request->input('point_b', $request->input('point_display_b'));
            if ($incPtA !== null) {
                $currentState['point_display_a'] = (string) $incPtA;
            }
            if ($incPtB !== null) {
                $currentState['point_display_b'] = (string) $incPtB;
            }

            // Idempotency: Jika sudah stopped, kembalikan response sukses secara idempoten
            if (($currentState['status'] ?? '') === 'stopped') {
                return response()->json([
                    'success' => true,
                    'already_stopped' => true,
                    'message' => 'Pertandingan ini telah dihentikan sebelumnya.',
                    'match_key' => $matchKey,
                    'status' => 'stopped',
                    'saved' => $currentState,
                    'correlation_id' => $correlationId,
                ])->header('X-Correlation-Id', $correlationId);
            }

            $prevVersion = (int) ($currentState['version'] ?? 0);
            $newVersion = $prevVersion + 1;
            $serverTimeMs = (int) round(microtime(true) * 1000);
            $eventId = 'stop_'.$newVersion.'_'.Str::random(8);

            // Dapatkan nama pemain yang keluar jika belum terisi
            if (empty($withdrawnPlayerNames) && ! empty($withdrawnPlayerIds)) {
                $withdrawnPlayerNames = $session->players
                    ->whereIn('player_id', $withdrawnPlayerIds)
                    ->pluck('nama', 'player_id')
                    ->toArray();
            }

            $scorePayload = array_merge($currentState, [
                'status' => 'stopped',
                'stop_type' => $stopType,
                'stop_reason' => $reason,
                'stopped_by_user_id' => Auth::id(),
                'stopped_by_name' => Auth::user()->nama ?? Auth::user()->name ?? 'Host',
                'stopped_at' => now()->toDateTimeString(),
                'withdrawn_player_ids' => $withdrawnPlayerIds,
                'withdrawn_player_names' => array_values($withdrawnPlayerNames),
                'player_reasons' => $playerReasons,
                'affected_player_id' => $request->input('affected_player_id'),
                'winner_team' => null, // Requirement 8: Jangan memutuskan pemenang sepihak!
                'version' => $newVersion,
                'server_version' => $newVersion,
                'last_event_id' => $eventId,
                'updated_at_ms' => $serverTimeMs,
                'updated_at' => now()->toDateTimeString(),
            ]);

            // 1. Simpan perubahan secara ATOMIK ke database
            DB::transaction(function () use ($session, $round, $courtIndex, $scorePayload, $newVersion, $eventId, $stopType, $isWithdraw, $isPause, $reason, $withdrawnPlayerIds, $playerReasons) {
                $match = ScoringService::ensureMatchAndParticipants($session, $round, $courtIndex);
                if ($match) {
                    $matchSummary = "Dihentikan: {$scorePayload['games_a']} - {$scorePayload['games_b']} ({$reason})";
                    $match->status_match = 'Stopped';
                    $match->hasil_pertandingan = $matchSummary;
                    $match->winner_team = null;
                    $match->waktu_selesai = now();
                    if (Schema::hasColumn('tb_match', 'version')) {
                        $match->version = $newVersion;
                    }
                    if (Schema::hasColumn('tb_match', 'last_event_id')) {
                        $match->last_event_id = $eventId;
                    }

                    if (Schema::hasColumn('tb_match', 'stop_type')) {
                        $match->stop_type = $stopType;
                    }
                    if (Schema::hasColumn('tb_match', 'stop_reason')) {
                        $match->stop_reason = $reason;
                    }
                    if (Schema::hasColumn('tb_match', 'stopped_by_user_id')) {
                        $match->stopped_by_user_id = Auth::id();
                    }
                    if (Schema::hasColumn('tb_match', 'stopped_at')) {
                        $match->stopped_at = now();
                    }
                    if (Schema::hasColumn('tb_match', 'withdrawn_player_ids')) {
                        $match->withdrawn_player_ids = json_encode($withdrawnPlayerIds);
                    }
                    if (Schema::hasColumn('tb_match', 'player_notes')) {
                        $match->player_notes = json_encode($playerReasons);
                    }
                    $match->save();

                    // Simpan baris set aktif di tb_score dengan status_score 'Stopped'
                    $setNumber = $scorePayload['set_number'] ?? 1;
                    Score::updateOrCreate(
                        [
                            'match_id' => $match->match_id,
                            'set_number' => $setNumber,
                        ],
                        [
                            'game_number' => 1,
                            'point_score_a' => (string) ($scorePayload['point_display_a'] ?? '0'),
                            'point_score_b' => (string) ($scorePayload['point_display_b'] ?? '0'),
                            'game_score_a' => (int) ($scorePayload['games_a'] ?? 0),
                            'game_score_b' => (int) ($scorePayload['games_b'] ?? 0),
                            'set_score_a' => (int) ($scorePayload['sets_a'] ?? 0),
                            'set_score_b' => (int) ($scorePayload['sets_b'] ?? 0),
                            'score_side_a' => (int) ($scorePayload['games_a'] ?? 0),
                            'score_side_b' => (int) ($scorePayload['games_b'] ?? 0),
                            'scoring_system' => (string) ($scorePayload['scoring_type'] ?? 'total_of_sets'),
                            'status_score' => 'Stopped',
                            'version' => $newVersion,
                            'last_event_id' => $eventId,
                        ]
                    );

                    // Catat EventLog persisten di tb_score
                    Score::create([
                        'match_id' => $match->match_id,
                        'set_number' => $setNumber,
                        'game_number' => $newVersion + 1000,
                        'point_score_a' => (string) ($scorePayload['point_display_a'] ?? '0'),
                        'point_score_b' => (string) ($scorePayload['point_display_b'] ?? '0'),
                        'game_score_a' => (int) ($scorePayload['games_a'] ?? 0),
                        'game_score_b' => (int) ($scorePayload['games_b'] ?? 0),
                        'set_score_a' => (int) ($scorePayload['sets_a'] ?? 0),
                        'set_score_b' => (int) ($scorePayload['sets_b'] ?? 0),
                        'score_side_a' => (int) ($scorePayload['games_a'] ?? 0),
                        'score_side_b' => (int) ($scorePayload['games_b'] ?? 0),
                        'scoring_system' => (string) ($scorePayload['scoring_type'] ?? 'total_of_sets'),
                        'status_score' => 'EventLog',
                        'version' => $newVersion,
                        'last_event_id' => $eventId,
                    ]);

                    // Perbarui status partisipasi pemain di tb_session_player jika kolom tersedia
                    if (($isWithdraw || $isPause) && ! empty($withdrawnPlayerIds) && Schema::hasTable('tb_session_player')) {
                        foreach ($withdrawnPlayerIds as $wPlayerId) {
                            $updateData = [];
                            if (Schema::hasColumn('tb_session_player', 'status')) {
                                $updateData['status'] = $isPause ? 'resting' : 'withdrawn';
                            }
                            if (Schema::hasColumn('tb_session_player', 'withdrawn_at')) {
                                $updateData['withdrawn_at'] = now();
                            }
                            if (Schema::hasColumn('tb_session_player', 'withdrawn_reason')) {
                                $updateData['withdrawn_reason'] = $playerReasons[$wPlayerId] ?? $reason;
                            }
                            if (Schema::hasColumn('tb_session_player', 'withdrawn_match_id')) {
                                $updateData['withdrawn_match_id'] = $match->match_id;
                            }
                            if (! empty($updateData)) {
                                DB::table('tb_session_player')
                                    ->where('session_id', $session->session_id)
                                    ->where('player_id', $wPlayerId)
                                    ->update($updateData);
                            }
                        }
                    }
                }
            });

            // 2. Sesuaikan sisa jadwal mendatang jika pemain keluar atau istirahat
            $scheduleAdjusted = false;
            $adjustedScheduleSummary = null;
            $adjustedSchedule = null;
            if ($isWithdraw && ! empty($withdrawnPlayerIds)) {
                try {
                    preg_match('/(\d+)/', $round, $rM);
                    $currRoundNum = isset($rM[1]) ? (int) $rM[1] : 1;
                    $adjustedSchedule = ScoringService::adjustFutureScheduleOnWithdrawal($session, $withdrawnPlayerIds, $currRoundNum);
                    $scheduleAdjusted = true;
                    $adjustedScheduleSummary = $adjustedSchedule['summary'] ?? null;
                } catch (\Throwable $e) {
                    Log::error("[stopMatch] [{$correlationId}] Schedule adjustment error: {$e->getMessage()}");
                }
            } elseif ($isPause && ! empty($withdrawnPlayerIds)) {
                try {
                    preg_match('/(\d+)/', $round, $rM);
                    $currRoundNum = isset($rM[1]) ? (int) $rM[1] : 1;
                    $adjustedSchedule = ScoringService::adjustFutureScheduleOnPause($session, $withdrawnPlayerIds, $currRoundNum);
                    $scheduleAdjusted = true;
                    $adjustedScheduleSummary = $adjustedSchedule['summary'] ?? null;
                } catch (\Throwable $e) {
                    Log::error("[stopMatch] [{$correlationId}] Pause schedule adjustment error: {$e->getMessage()}");
                }
            }

            // 3. Simpan ke Cache setelah DB commit berhasil
            $sessionLock = Cache::lock("scoring.session.{$gameId}", 5);
            $sessionLock->block(3);
            try {
                $scores = Cache::get($cacheKey, []);
                $scores[$matchKey] = $scorePayload;
                if ($courtCount === 1) {
                    $scores[$round] = $scorePayload;
                    $scores["{$round}_court_1"] = $scorePayload;
                }
                if ($isWithdraw) {
                    $existingWithdrawn = $scores['_meta']['withdrawn_players'] ?? [];
                    $scores['_meta']['withdrawn_players'] = array_values(array_unique(array_merge($existingWithdrawn, $withdrawnPlayerIds)));
                } elseif ($isPause) {
                    $existingPaused = $scores['_meta']['paused_players'] ?? [];
                    $scores['_meta']['paused_players'] = array_values(array_unique(array_merge($existingPaused, $withdrawnPlayerIds)));
                }
                Cache::put($cacheKey, $scores, now()->addHours(4));
                Cache::put("scoring.game_{$gameId}.{$matchKey}", $scorePayload, now()->addHours(4));
            } finally {
                $sessionLock->release();
            }
        } catch (\Throwable $e) {
            Log::error("[Stop Match Error] [{$correlationId}] {$e->getMessage()}", [
                'exception' => $e,
                'game_id' => $gameId,
                'match_key' => $matchKey,
                'round' => $round,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kendala teknis pada server saat mengakhiri pertandingan. Silakan coba lagi.',
                'correlation_id' => $correlationId,
            ], 500)->header('X-Correlation-Id', $correlationId);
        } finally {
            $scoreLock->release();
        }

        // 4. Broadcast Realtime di luar lock
        $this->broadcastScoreUpdateRealtime($gameId, $matchKey, $scorePayload);
        if ($scheduleAdjusted && isset($adjustedSchedule['rounds'])) {
            $this->broadcastDrawingShuffledRealtime($gameId, $adjustedSchedule['drawingData'] ?? [], $adjustedSchedule['rounds']);
        }

        $hasInsufficient = ! empty($adjustedScheduleSummary['insufficient']);
        $insufficientWarning = $hasInsufficient ? 'Jumlah pemain aktif tidak mencukupi untuk melanjutkan ronde berikutnya.' : null;
        $stoppedMsg = $isPause
            ? 'Pertandingan berhasil dihentikan. Pemain dipindahkan ke bangku cadangan untuk ronde berikutnya.'
            : 'Pertandingan berhasil dihentikan.';

        return response()->json([
            'success' => true,
            'message' => $stoppedMsg,
            'match_key' => $matchKey,
            'status' => 'stopped',
            'stop_type' => $stopType,
            'saved' => $scorePayload,
            'schedule_adjusted' => $scheduleAdjusted,
            'schedule_summary' => $adjustedScheduleSummary,
            'has_insufficient_players' => $hasInsufficient,
            'warning' => $insufficientWarning,
            'correlation_id' => $correlationId,
        ])->header('X-Correlation-Id', $correlationId);
    }

    /**
     * Mengaktifkan kembali pemain yang sebelumnya istirahat sementara (paused / resting di bench) (khusus Host).
     */
    public function reactivatePlayer(Request $request)
    {
        if (! Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Silakan login terlebih dahulu.',
            ], 403);
        }

        $gameId = (int) ($request->input('game_id') ?: $request->input('session_id'));
        if (! $gameId) {
            return response()->json([
                'success' => false,
                'message' => 'ID Sesi diperlukan.',
            ], 422);
        }

        $session = SessionModel::with(['players.user', 'courts', 'sport', 'host'])->find($gameId);
        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi tidak ditemukan.',
            ], 404);
        }

        if (! $this->isHostForSession($session)) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Hanya Host yang dapat mengaktifkan kembali pemain.',
            ], 403);
        }

        $request->validate([
            'player_id' => 'required|integer',
            'round' => 'nullable|string',
        ]);

        $playerId = (int) $request->input('player_id');
        $player = Player::find($playerId);
        $playerName = $player ? $player->nama : "Pemain #{$playerId}";

        // 1. Update tb_session_player: status = 'active'
        if (Schema::hasTable('tb_session_player')) {
            $updateData = [];
            if (Schema::hasColumn('tb_session_player', 'status')) {
                $updateData['status'] = 'active';
            }
            if (Schema::hasColumn('tb_session_player', 'withdrawn_at')) {
                $updateData['withdrawn_at'] = null;
            }
            if (Schema::hasColumn('tb_session_player', 'withdrawn_reason')) {
                $updateData['withdrawn_reason'] = null;
            }
            if (Schema::hasColumn('tb_session_player', 'withdrawn_match_id')) {
                $updateData['withdrawn_match_id'] = null;
            }
            if (! empty($updateData)) {
                DB::table('tb_session_player')
                    ->where('session_id', $session->session_id)
                    ->where('player_id', $playerId)
                    ->update($updateData);
            }
        }

        // 2. Update Cache scoring.game_{id}: hapus dari paused_players
        $cacheKey = "scoring.game_{$gameId}";
        $scores = Cache::get($cacheKey, []);
        $existingPaused = $scores['_meta']['paused_players'] ?? [];
        if (is_array($existingPaused)) {
            $scores['_meta']['paused_players'] = array_values(array_filter($existingPaused, fn ($id) => (int) $id !== $playerId));
            Cache::put($cacheKey, $scores, now()->addHours(4));
        }

        // 3. Tentukan ronde aktif
        $roundStr = (string) ($request->input('round') ?: ($scores['_meta']['active_round'] ?? 'round_1'));
        preg_match('/(\d+)/', $roundStr, $rM);
        $currRoundNum = isset($rM[1]) ? (int) $rM[1] : 1;

        // 4. Sesuaikan jadwal ronde mendatang dengan memasukkan kembali pemain ini
        $adjusted = ScoringService::adjustFutureScheduleOnReactivation($session, $playerId, $currRoundNum);

        // 5. Broadcast perubahan drawing ke seluruh client
        if (isset($adjusted['rounds'])) {
            $this->broadcastDrawingShuffledRealtime($gameId, $adjusted['drawingData'] ?? [], $adjusted['rounds']);
        }

        return response()->json([
            'success' => true,
            'message' => "Pemain {$playerName} telah siap dan dimasukkan kembali ke rotasi pertandingan!",
            'player_id' => $playerId,
            'player_name' => $playerName,
            'schedule_version' => $adjusted['schedule_version'] ?? 1,
            'active_players_count' => $adjusted['active_players_count'] ?? null,
            'rounds' => $adjusted['rounds'] ?? [],
        ]);
    }

    /**
     * Broadcast update drawing yang diacak/disesuaikan ke channel Supabase Realtime.
     */
    protected function broadcastDrawingShuffledRealtime(int $gameId, array $drawingData, array $rounds, array $participantsMap = []): void
    {
        try {
            $url = rtrim(config('services.supabase.url', ''), '/').'/realtime/v1/api/broadcast';
            $key = config('services.supabase.service_key', config('services.supabase.key'));
            if (empty($url) || empty($key)) {
                return;
            }

            $headers = [
                'apikey' => $key,
                'Content-Type' => 'application/json',
            ];
            if (str_starts_with($key, 'ey')) {
                $headers['Authorization'] = 'Bearer '.$key;
            }

            Http::withOptions(['verify' => $this->resolveCaBundle()])
                ->withHeaders($headers)
                ->timeout(2)
                ->post($url, [
                    'messages' => [
                        [
                            'topic' => "session_{$gameId}",
                            'event' => 'drawing_shuffled',
                            'payload' => [
                                'session_id' => $gameId,
                                'drawingData' => $drawingData,
                                'rounds' => $rounds,
                                'participantsMap' => $participantsMap,
                                'timestamp' => now()->toIso8601String(),
                            ],
                        ],
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::debug("Supabase realtime drawing shuffled broadcast skipped: {$e->getMessage()}");
        }
    }

    /**
     * Lanjut ke set/ronde berikutnya (khusus Host).
     * Memvalidasi bahwa semua court pada ronde aktif saat ini telah selesai sebelum membuka ronde berikutnya.
     */
    public function nextRound(Request $request)
    {
        if (! Auth::check()) {
            abort(403, 'Akses ditolak. Silakan login terlebih dahulu.');
        }

        if (! $request->has('game_id')) {
            $routeId = $request->route('id') ?: $request->route('session_id') ?: $request->query('game_id') ?: $request->route('game_id');
            if (! $routeId) {
                foreach ($request->query() as $qKey => $qVal) {
                    if (is_numeric($qKey) && empty($qVal)) {
                        $routeId = (int) $qKey;
                        break;
                    }
                }
            }
            if ($routeId) {
                $request->merge(['game_id' => (int) $routeId]);
            }
        }

        $request->validate([
            'game_id' => 'required|integer',
            'current_round' => 'required|string',
            'next_round' => 'required|string',
        ]);

        $gameId = $request->integer('game_id');
        $session = SessionModel::findOrFail($gameId);
        if (! $this->isHostForSession($session)) {
            abort(403, 'Akses ditolak. Hanya Host yang dapat melanjutkan ke set/ronde berikutnya.');
        }

        $currentRoundRaw = $request->string('current_round')->toString();
        $nextRoundRaw = $request->string('next_round')->toString();
        $currNum = preg_replace('/[^0-9]/', '', $currentRoundRaw) ?: '1';
        $nextNum = preg_replace('/[^0-9]/', '', $nextRoundRaw) ?: '2';
        $currentRound = "round_{$currNum}";
        $nextRound = "round_{$nextNum}";

        $game = $this->getGameData($gameId);
        $scoringSystem = ScoringService::detectScoringSystem($game['scoring_system'] ?? '');
        $cacheKey = "scoring.game_{$gameId}";
        $savedScores = Cache::get($cacheKey, []);

        $isTeamFormat = str_contains(strtolower($game['match_format'] ?? ''), 'team');
        $unitLabel = $isTeamFormat ? 'Set' : 'Ronde';

        // 0a. Validasi batas akhir sesi: jika sudah pada ronde terakhir, jangan buat ronde N+1 atau fallback
        $cachedSchedule = Cache::get("drawing.schedule_{$gameId}", []);
        $drawingRounds = ! empty($game['drawing']) ? $game['drawing'] : ($cachedSchedule['rounds'] ?? []);
        $totalRounds = ! empty($drawingRounds) ? count($drawingRounds) : 0;
        if ($totalRounds > 0 && ((int) $currNum >= $totalRounds || (int) $nextNum > $totalRounds)) {
            return redirect()->route('scoring.live', [
                'id' => $gameId,
                'format' => request('format', $game['match_format'] ?? 'Americano'),
                'round' => $currentRound,
                'court' => (int) $request->input('court', 0),
            ])->with('warning', 'Seluruh set/ronde telah selesai. Silakan selesaikan sesi.');
        }

        // 0b. Validasi urutan linier: hanya boleh lanjut ke tepat 1 ronde berikutnya (cegah loncat ronde)
        if ((int) $nextNum !== ((int) $currNum + 1)) {
            return redirect()->route('scoring.live', [
                'id' => $gameId,
                'format' => request('format', $game['match_format'] ?? 'Americano'),
                'round' => $currentRound,
                'court' => (int) $request->input('court', 0),
            ])->with('error', 'Perpindahan ronde harus dilakukan secara berurutan.');
        }

        // 0c. Idempotency / Retry guard: jika transisi ronde ini sudah berhasil dieksekusi sebelumnya
        if (($savedScores['_meta']['active_round'] ?? '') === $nextRound) {
            if ($request->input('redirect_to') === 'live') {
                return redirect()->route('scoring.live', [
                    'id' => $gameId,
                    'format' => request('format', $game['match_format'] ?? 'Americano'),
                    'round' => $nextRound,
                    'court' => (int) $request->input('court', 0),
                ])->with('success', "Berhasil lanjut ke {$unitLabel} {$nextNum}!");
            }

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => "Membuka susunan pertandingan {$unitLabel} {$nextNum}...",
                    'redirect' => route('games.drawing', [
                        'id' => $gameId,
                        'round' => $nextNum,
                    ]),
                    'next_round' => $nextRound,
                    'next_round_num' => (int) $nextNum,
                ]);
            }

            return redirect()->route('games.drawing', [
                'id' => $gameId,
                'round' => $nextNum,
            ])->with('success', "{$unitLabel} {$currNum} selesai! Silakan periksa susunan pemain & jadwal {$unitLabel} {$nextNum}.");
        }

        // 1. Validasi: Ronde aktif saat ini harus benar-benar selesai di SELURUH court
        if (! $this->isRoundFullyCompleted($game, $currentRound, $savedScores)) {
            return redirect()->route('scoring.live', [
                'id' => $gameId,
                'format' => request('format', $game['match_format'] ?? 'Americano'),
                'round' => $currentRound,
                'court' => (int) $request->input('court', 0),
            ])->with('error', 'Set/Ronde saat ini belum selesai. Selesaikan seluruh pertandingan terlebih dahulu.')
                ->with('warning', 'Set/Ronde saat ini belum selesai. Selesaikan seluruh pertandingan terlebih dahulu.');
        }

        // 2. Validasi: Ronde tujuan harus unlocked secara berurutan
        $roundAccess = $this->getRoundAccess($game, $scoringSystem, $savedScores);
        if (! ($roundAccess[$nextRound] ?? false)) {
            return redirect()->route('scoring.live', [
                'id' => $gameId,
                'format' => request('format', $game['match_format'] ?? 'Americano'),
                'round' => $currentRound,
                'court' => (int) $request->input('court', 0),
            ])->with('error', 'Set/Ronde saat ini belum selesai. Selesaikan seluruh pertandingan terlebih dahulu.')
                ->with('warning', 'Set/Ronde saat ini belum selesai. Selesaikan seluruh pertandingan terlebih dahulu.');
        }

        // 2b. Validasi: Ronde tujuan tidak memiliki kendala pemain tidak mencukupi atau tim tidak lengkap
        $cachedSchedule = Cache::get("drawing.schedule_{$gameId}", []);
        $nextRoundData = $cachedSchedule[$nextRound]
            ?? ($cachedSchedule['rounds'][$nextRound]
            ?? ($cachedSchedule['rounds'][(int) $nextNum]
            ?? ($game['drawing'][$nextRound] ?? [])));

        if (! empty($nextRoundData['has_insufficient_players'])) {
            $reason = $nextRoundData['insufficient_reason'] ?? 'Pemain aktif tidak mencukupi untuk memulai ronde ini.';

            return redirect()->route('scoring.live', [
                'id' => $gameId,
                'format' => request('format', $game['match_format'] ?? 'Americano'),
                'round' => $currentRound,
                'court' => (int) $request->input('court', 0),
            ])->with('error', "Ronde berikutnya belum dapat dimulai: {$reason}")
                ->with('warning', "Ronde berikutnya belum dapat dimulai: {$reason}");
        }

        foreach (($nextRoundData['matches'] ?? []) as $nm) {
            if (! empty($nm['incomplete_team']) || (isset($nm['can_activate']) && $nm['can_activate'] === false)) {
                $reason = $nm['invalidation_reason'] ?? 'Terdapat pasangan tim yang tidak lengkap karena pemain keluar. Perlu penataan tim oleh Host.';

                return redirect()->route('scoring.live', [
                    'id' => $gameId,
                    'format' => request('format', $game['match_format'] ?? 'Americano'),
                    'round' => $currentRound,
                    'court' => (int) $request->input('court', 0),
                ])->with('error', "Ronde berikutnya belum dapat dimulai: {$reason}")
                    ->with('warning', "Ronde berikutnya belum dapat dimulai: {$reason}");
            }
        }

        // 3. Update active_round pada metadata Cache
        $savedScores['_meta'] = array_merge($savedScores['_meta'] ?? [], [
            'active_round' => $nextRound,
            'updated_at' => now()->toDateTimeString(),
        ]);
        Cache::put($cacheKey, $savedScores, now()->addHours(4));

        // 3b. Simpan transisi ke database jika kolom persisten tersedia
        if (Schema::hasColumn('tb_session', 'active_round')) {
            try {
                DB::table('tb_session')
                    ->where('session_id', $gameId)
                    ->update(['active_round' => $nextRound]);
            } catch (\Throwable $e) {
                Log::warning("[nextRound] Could not persist active_round to tb_session: {$e->getMessage()}");
            }
        }

        // 4. Broadcast perpindahan ronde ke seluruh penonton/player via Supabase Realtime
        $this->broadcastRoundAdvancedRealtime($gameId, $currentRound, $nextRound);

        if ($request->input('redirect_to') === 'live') {
            return redirect()->route('scoring.live', [
                'id' => $gameId,
                'format' => request('format', $game['match_format'] ?? 'Americano'),
                'round' => $nextRound,
                'court' => (int) $request->input('court', 0),
            ])->with('success', "Berhasil lanjut ke {$unitLabel} {$nextNum}!");
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "{$unitLabel} {$currNum} selesai! Membuka susunan pertandingan {$unitLabel} {$nextNum}...",
                'redirect' => route('games.drawing', [
                    'id' => $gameId,
                    'round' => $nextNum,
                ]),
                'next_round' => $nextRound,
                'next_round_num' => (int) $nextNum,
            ]);
        }

        return redirect()->route('games.drawing', [
            'id' => $gameId,
            'round' => $nextNum,
        ])->with('success', "{$unitLabel} {$currNum} selesai! Silakan periksa susunan pemain & jadwal {$unitLabel} {$nextNum}.");
    }

    /**
     * Tandai sesi sebagai selesai dan redirect ke halaman recap.
     * Simpan juga data ke database: tb_drawing, tb_match, tb_score, tb_playing_history.
     */
    public function finishSession(Request $request)
    {
        // Hanya host yang boleh menyelesaikan sesi
        if (! Auth::check()) {
            return redirect()->back()->withErrors(['auth' => 'Akses ditolak. Silakan login terlebih dahulu.']);
        }

        $request->validate([
            'game_id' => 'required|integer',
            'round' => 'required|string',
        ]);

        $gameId = $request->integer('game_id');
        $session = SessionModel::findOrFail($gameId);
        if (! $this->isHostForSession($session)) {
            abort(403, 'Akses ditolak. Hanya Host yang dapat menyelesaikan sesi.');
        }

        $round = $request->string('round')->toString();
        $matchKey = $request->input('match_key');
        if (! $matchKey) {
            $courtNum = $request->integer('court', 0);
            $matchKey = ($courtNum > 0) ? "{$round}_court_{$courtNum}" : $round;
        }

        $game = $this->getGameData($gameId);
        $scoringSystemName = $request->input('scoring_system', $game['scoring_system'] ?? 'Total of 3');
        $system = ScoringService::detectScoringSystem($scoringSystemName);

        $allRoundKeys = array_keys($game['drawing'] ?? []);
        $lastRoundKey = ! empty($allRoundKeys) ? end($allRoundKeys) : $round;
        if (! empty($allRoundKeys) && $round !== $lastRoundKey) {
            return redirect()->back()->withErrors([
                'score' => 'Sesi belum mencapai ronde terakhir. Selesaikan seluruh ronde pertandingan terlebih dahulu.',
            ]);
        }

        // Ambil data skor dari request atau fallback ke cache
        $cacheKey = "scoring.game_{$gameId}";
        $scores = Cache::get($cacheKey, []);
        $roundAccess = $this->getRoundAccess($game, $system, $scores);
        if (! ($roundAccess[$round] ?? false)) {
            return redirect()->back()->withErrors([
                'score' => 'Ronde sebelumnya belum selesai.',
            ]);
        }
        $prev = $scores[$matchKey] ?? ($scores[$round] ?? []);

        $scoreA = $request->integer('score_a', $prev['score_a'] ?? 0);
        $scoreB = $request->integer('score_b', $prev['score_b'] ?? 0);
        $setsA = $request->integer('sets_a', $prev['sets_a'] ?? 0);
        $setsB = $request->integer('sets_b', $prev['sets_b'] ?? 0);
        $gamesA = $request->integer('games_a', $prev['games_a'] ?? 0);
        $gamesB = $request->integer('games_b', $prev['games_b'] ?? 0);
        $winnerTeam = $request->input('winner_team', $prev['winner_team'] ?? null);
        $isWalkover = $request->boolean('is_walkover', false);

        if (! $isWalkover && ! ScoringService::isMatchCompleted($gamesA, $gamesB, $system)) {
            return redirect()->back()->withErrors([
                'score' => "Pertandingan belum memenuhi syarat selesai untuk sistem skor {$system['label']} (skor saat ini: {$gamesA}–{$gamesB}).",
            ]);
        }

        $setHistoryRaw = $request->input('set_history', $prev['set_history'] ?? []);
        if (is_string($setHistoryRaw)) {
            $setHistory = json_decode($setHistoryRaw, true) ?: [];
        } else {
            $setHistory = is_array($setHistoryRaw) ? $setHistoryRaw : [];
        }

        // Rebuild and sanitize setHistory to ensure strictly monotonic set numbers
        $cleanHistory = [];
        $currentSet = 1;
        foreach ($setHistory as $sh) {
            $cleanHistory[] = [
                'set' => $currentSet++,
                'score_a' => (int) ($sh['score_a'] ?? 0),
                'score_b' => (int) ($sh['score_b'] ?? 0),
            ];
        }

        if ($system['is_sets']) {
            // Jika set_history kosong tetapi ada game yang dimainkan, tambahkan sebagai set 1
            if (empty($cleanHistory) && ($gamesA > 0 || $gamesB > 0)) {
                $cleanHistory[] = [
                    'set' => $currentSet,
                    'score_a' => $gamesA,
                    'score_b' => $gamesB,
                ];
            }
            $setHistory = $cleanHistory;

            // Hitung sets dari setHistory
            if (! empty($setHistory)) {
                $calcSetsA = 0;
                $calcSetsB = 0;
                foreach ($setHistory as $s) {
                    if (($s['score_a'] ?? 0) > ($s['score_b'] ?? 0)) {
                        $calcSetsA++;
                    } elseif (($s['score_b'] ?? 0) > ($s['score_a'] ?? 0)) {
                        $calcSetsB++;
                    }
                }
                if ($calcSetsA > 0 || $calcSetsB > 0) {
                    $setsA = $calcSetsA;
                    $setsB = $calcSetsB;
                }
            }
            if ($setsA === 0 && $setsB === 0 && ($gamesA > 0 || $gamesB > 0)) {
                if ($gamesA >= $gamesB) {
                    $setsA = 1;
                } else {
                    $setsB = 1;
                }
            }

            if (! $winnerTeam) {
                $winnerTeam = $setsA >= $setsB ? 'Team A' : 'Team B';
            }
        } else {
            if (! $winnerTeam) {
                $winnerTeam = $gamesA >= $gamesB ? 'Team A' : 'Team B';
            }
        }

        // 1. Simpan skor final ke Cache (shared)
        $prevVersion = (int) ($scores[$matchKey]['version'] ?? 0);
        $newVersion = $prevVersion + 1;
        $serverTimeMs = (int) round(microtime(true) * 1000);

        $scorePayload = [
            'version' => $newVersion,
            'updated_at_ms' => $serverTimeMs,
            'score_a' => $system['is_sets'] ? $setsA : $gamesA,
            'score_b' => $system['is_sets'] ? $setsB : $gamesB,
            'point_display_a' => $request->input('point_display_a', $prev['point_display_a'] ?? '0'),
            'point_display_b' => $request->input('point_display_b', $prev['point_display_b'] ?? '0'),
            'set_number' => count($setHistory) > 0 ? count($setHistory) : 1,
            'sets_a' => $setsA,
            'sets_b' => $setsB,
            'games_a' => $gamesA,
            'games_b' => $gamesB,
            'set_history' => $setHistory,
            'scoring_type' => $system['type'],
            'scoring_system' => $system['type'],
            'winner_team' => $winnerTeam,
            'status' => 'completed',
            'updated_at' => now()->toDateTimeString(),
        ];

        $isMultiCourt = str_contains($matchKey, '_court_');
        $scores[$matchKey] = $scorePayload;
        if (! $isMultiCourt) {
            $scores[$round] = $scorePayload;
        }

        // Tandai game sebagai finished
        $scores['_meta'] = [
            'finished_at' => now()->toDateTimeString(),
            'last_round_key' => $round,
            'status' => 'finished',
        ];

        Cache::put($cacheKey, $scores, now()->addHours(4));
        Cache::put("scoring.game_{$gameId}.{$matchKey}", $scorePayload, now()->addHours(4));

        // 2. Simpan permanen ke Database jika session ada di tb_session
        try {
            $session = $session->load(['players', 'courts']);
            if ($session) {
                // Update status session
                $session->status_session = 'Finished';
                $session->save();

                $courtIndex = 0;
                if (preg_match('/court_(\d+)/', $matchKey, $cm)) {
                    $courtIndex = max(0, ((int) $cm[1]) - 1);
                } elseif ($request->has('court')) {
                    $courtIndex = max(0, $request->integer('court') - 1);
                }

                $matchSummary = $system['is_sets']
                    ? "Set Score {$setsA} - {$setsB}"
                    : "Game Score {$gamesA} - {$gamesB}";
                if ($isWalkover) {
                    $matchSummary = "Walkover ({$gamesA} - {$gamesB})";
                }

                // Reuse tb_match & participants deterministically (tidak membuat duplikat)
                $match = ScoringService::ensureMatchAndParticipants($session, $round, $courtIndex);
                if ($match) {
                    $match->status_match = 'Completed';
                    $match->waktu_selesai = now();
                    $match->hasil_pertandingan = $matchSummary;
                    $match->winner_team = $winnerTeam;
                    $match->save();
                }

                if ($match) {
                    // Simpan Scores ke tb_score
                    Score::where('match_id', $match->match_id)->delete();
                    if ($system['is_sets'] && ! empty($setHistory)) {
                        foreach ($setHistory as $sItem) {
                            Score::create([
                                'match_id' => $match->match_id,
                                'set_number' => (int) ($sItem['set'] ?? 1),
                                'game_number' => 1,
                                'point_score_a' => '40',
                                'point_score_b' => '0',
                                'game_score_a' => (int) ($sItem['score_a'] ?? 0),
                                'game_score_b' => (int) ($sItem['score_b'] ?? 0),
                                'set_score_a' => $setsA,
                                'set_score_b' => $setsB,
                                'score_side_a' => (int) ($sItem['score_a'] ?? 0),
                                'score_side_b' => (int) ($sItem['score_b'] ?? 0),
                                'scoring_system' => $system['type'],
                                'status_score' => 'Final',
                            ]);
                        }
                    } else {
                        Score::create([
                            'match_id' => $match->match_id,
                            'set_number' => 1,
                            'game_number' => 1,
                            'point_score_a' => 'Game',
                            'point_score_b' => '0',
                            'game_score_a' => $gamesA,
                            'game_score_b' => $gamesB,
                            'set_score_a' => $setsA,
                            'set_score_b' => $setsB,
                            'score_side_a' => $gamesA,
                            'score_side_b' => $gamesB,
                            'scoring_system' => $system['type'],
                            'status_score' => 'Final',
                        ]);
                    }

                    // Catat ke Playing History
                    // Bangun lookup: nama bersih → player_id (dari session players yang ada di DB)
                    $matchContext = ScoringService::buildMatchContext($game, $round, $courtIndex);
                    $teamAPlayers = $matchContext['team_a'] ?? [];
                    $teamBPlayers = $matchContext['team_b'] ?? [];

                    $totalGamesPlayed = $system['is_sets']
                        ? array_sum(array_column($setHistory, 'score_a')) + array_sum(array_column($setHistory, 'score_b'))
                        : ($gamesA + $gamesB);

                    $sessionPlayerMap = $session->players->keyBy(fn ($p) => ScoringService::cleanPlayerName($p->nama));

                    $isTeamAWinner = ($winnerTeam === 'Team A');

                    // Tentukan apakah ini Single (1 pemain per sisi) atau Double (2+ pemain per sisi)
                    $isSingleMatch = (count($teamAPlayers) === 1 && count($teamBPlayers) === 1);

                    // Resolve player objects dari nama
                    $resolvePlayer = function (string $name) use ($sessionPlayerMap): ?object {
                        return $sessionPlayerMap->get(ScoringService::cleanPlayerName($name));
                    };

                    foreach ($teamAPlayers as $pName) {
                        $player = $resolvePlayer($pName);
                        if (! $player) {
                            continue;
                        }

                        if ($isSingleMatch) {
                            // Single: tidak ada partner; opponent adalah satu-satunya pemain di sisi B
                            $partnerPlayerId = null;
                            $opponentPlayerObj = $resolvePlayer($teamBPlayers[0] ?? '');
                            $opponentPlayerId = $opponentPlayerObj?->player_id;
                        } else {
                            // Double: partner adalah rekan setim di sisi A (selain diri sendiri)
                            $partnerName = collect($teamAPlayers)->first(fn ($n) => ScoringService::cleanPlayerName($n) !== ScoringService::cleanPlayerName($pName));
                            $partnerPlayerObj = $partnerName ? $resolvePlayer($partnerName) : null;
                            $partnerPlayerId = $partnerPlayerObj?->player_id;
                            // Opponent: pemain pertama di sisi B yang ada di DB
                            $opponentPlayerObj = $resolvePlayer($teamBPlayers[0] ?? '');
                            $opponentPlayerId = $opponentPlayerObj?->player_id;
                        }

                        PlayingHistory::updateOrCreate(
                            [
                                'player_id' => $player->player_id,
                                'match_id' => $match->match_id,
                            ],
                            [
                                'jumlah_permainan' => max(1, $totalGamesPlayed),
                                'waktu_permainan' => now(),
                                'status_permainan' => $isTeamAWinner ? 'Menang' : 'Kalah',
                                'partner_player_id' => $partnerPlayerId,
                                'opponent_player_id' => $opponentPlayerId,
                            ]
                        );
                    }

                    foreach ($teamBPlayers as $pName) {
                        $player = $resolvePlayer($pName);
                        if (! $player) {
                            continue;
                        }

                        if ($isSingleMatch) {
                            // Single: tidak ada partner; opponent adalah satu-satunya pemain di sisi A
                            $partnerPlayerId = null;
                            $opponentPlayerObj = $resolvePlayer($teamAPlayers[0] ?? '');
                            $opponentPlayerId = $opponentPlayerObj?->player_id;
                        } else {
                            // Double: partner adalah rekan setim di sisi B (selain diri sendiri)
                            $partnerName = collect($teamBPlayers)->first(fn ($n) => ScoringService::cleanPlayerName($n) !== ScoringService::cleanPlayerName($pName));
                            $partnerPlayerObj = $partnerName ? $resolvePlayer($partnerName) : null;
                            $partnerPlayerId = $partnerPlayerObj?->player_id;
                            // Opponent: pemain pertama di sisi A yang ada di DB
                            $opponentPlayerObj = $resolvePlayer($teamAPlayers[0] ?? '');
                            $opponentPlayerId = $opponentPlayerObj?->player_id;
                        }

                        PlayingHistory::updateOrCreate(
                            [
                                'player_id' => $player->player_id,
                                'match_id' => $match->match_id,
                            ],
                            [
                                'jumlah_permainan' => max(1, $totalGamesPlayed),
                                'waktu_permainan' => now(),
                                'status_permainan' => ! $isTeamAWinner ? 'Menang' : 'Kalah',
                                'partner_player_id' => $partnerPlayerId,
                                'opponent_player_id' => $opponentPlayerId,
                            ]
                        );
                    }
                }
            }
        } catch (\Throwable $e) {
            // Log warning, don't crash redirect
            Log::warning("Failed to persist score to database: {$e->getMessage()}");
        }

        // Broadcast penyelesaian sesi pertandingan ke seluruh penonton/player
        $this->broadcastSessionFinishedRealtime($gameId, route('scoring.recap', $gameId));

        return redirect()
            ->route('scoring.recap', $gameId)
            ->with('success', 'Pertandingan selesai! Hasil skor berhasil disimpan.');
    }

    /**
     * Podium & Klasemen Akhir.
     * Menghitung akumulasi poin/game/set semua pemain dari rounds yang sudah selesai.
     */
    public function recap($id = 1)
    {
        $game = $this->getGameData($id);
        $game['drawing'] = $this->resolveActualMatchHistory((int) $id, $game['drawing'] ?? []);
        $scoringSystem = ScoringService::detectScoringSystem($game['scoring_system'] ?? '');

        // Ambil skor tersimpan dari Cache (shared)
        $cacheKey = "scoring.game_{$game['id']}";
        $savedScores = Cache::get($cacheKey, []);

        // Lengkapi cache dari database agar ronde yang tidak ada/stale tetap terbaca.
        try {
            $drawingRecord = Drawing::where('session_id', $game['id'])->first();
            if ($drawingRecord) {
                $matches = GameMatch::with('scores')->where('drawing_id', $drawingRecord->drawing_id)->get();
                $dbScoresRecovered = false;
                $session = SessionModel::with('courts')->find($game['id']);
                $courtCount = $session ? max(1, $session->courts->count()) : 1;

                foreach ($matches as $match) {
                    $roundNumber = intdiv(max(0, (int) $match->nomor_match - 1), $courtCount) + 1;
                    $courtNumber = (($match->nomor_match - 1) % $courtCount) + 1;
                    $rKey = "round_{$roundNumber}";
                    $scoreKey = ScoringService::buildMatchKey($rKey, $courtNumber, $courtCount);
                    $authScores = ScoringService::getAuthoritativeScoresForMatch($match->scores);
                    $mSetsA = 0;
                    $mSetsB = 0;
                    $mGamesA = 0;
                    $mGamesB = 0;
                    $mSetHistory = [];

                    foreach ($authScores as $sc) {
                        $sa = (int) ($sc->set_score_a ?? 0);
                        $sb = (int) ($sc->set_score_b ?? 0);
                        $ga = (int) ($sc->game_score_a ?: ($sc->score_side_a ?? 0));
                        $gb = (int) ($sc->game_score_b ?: ($sc->score_side_b ?? 0));

                        $mSetsA = max($mSetsA, $sa);
                        $mSetsB = max($mSetsB, $sb);
                        $mGamesA += $ga;
                        $mGamesB += $gb;

                        $mSetHistory[] = [
                            'set' => (int) ($sc->set_number ?? 1),
                            'score_a' => $ga,
                            'score_b' => $gb,
                        ];
                    }

                    $isStopped = ($match->status_match === 'Stopped');
                    if ($authScores->isNotEmpty() || $match->status_match === 'Completed' || $isStopped) {
                        $dbScoresRecovered = true;

                        // Tentukan pemenang secara objektif (hindari badge WIN sepihak jika hasil seri atau dihentikan)
                        $winnerTeam = $match->winner_team;
                        if ($isStopped) {
                            $winnerTeam = null; // Status dihentikan tidak menentukan pemenang sepihak
                        } elseif (empty($winnerTeam)) {
                            if ($scoringSystem['is_sets'] && $mSetsA !== $mSetsB) {
                                $winnerTeam = $mSetsA > $mSetsB ? 'Team A' : 'Team B';
                            } elseif ($mGamesA !== $mGamesB) {
                                $winnerTeam = $mGamesA > $mGamesB ? 'Team A' : 'Team B';
                            } else {
                                $winnerTeam = null; // Draw / Seri
                            }
                        }

                        $savedScores[$scoreKey] = [
                            'score_a' => $scoringSystem['is_sets'] ? $mSetsA : $mGamesA,
                            'score_b' => $scoringSystem['is_sets'] ? $mSetsB : $mGamesB,
                            'point_display_a' => '0',
                            'point_display_b' => '0',
                            'set_number' => max(1, count($mSetHistory)),
                            'sets_a' => $mSetsA,
                            'sets_b' => $mSetsB,
                            'games_a' => $mGamesA,
                            'games_b' => $mGamesB,
                            'set_history' => ScoringService::sanitizeSetHistory($mSetHistory),
                            'scoring_type' => $scoringSystem['type'],
                            'scoring_system' => $scoringSystem['type'],
                            'winner_team' => $winnerTeam,
                            'status' => $isStopped ? 'stopped' : (($match->status_match === 'Completed') ? 'completed' : 'in_progress'),
                            'stop_type' => $match->stop_type,
                            'stop_reason' => $match->stop_reason,
                            'stopped_by_user_id' => $match->stopped_by_user_id,
                            'stopped_at' => $match->stopped_at ? $match->stopped_at->toDateTimeString() : null,
                            'withdrawn_player_ids' => $match->withdrawn_player_ids ?? [],
                            'player_notes' => $match->player_notes ?? [],
                            'updated_at' => $match->updated_at ? $match->updated_at->toDateTimeString() : now()->toDateTimeString(),
                        ];
                        if (! $isStopped && $match->status_match === 'Completed') {
                            $savedScores['_meta'] = [
                                'finished_at' => $match->updated_at ? $match->updated_at->toDateTimeString() : now()->toDateTimeString(),
                                'last_round_key' => $rKey,
                                'status' => 'finished',
                            ];
                        }
                    }
                }

                if ($dbScoresRecovered) {
                    Cache::put($cacheKey, $savedScores, now()->addHours(4));
                }
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to recover scores from DB in recap: {$e->getMessage()}");
        }

        // Sinkronisasi effective round scores
        $effectiveScores = ScoringService::getEffectiveScores($game, $savedScores);

        // Hitung ranking semua pemain
        $rankedPlayers = ScoringService::calculateRecap($game, $effectiveScores);

        // Cek status sesi dari database & jumlah match selesai
        $sessionModel = SessionModel::find($game['id']);
        $sessionStatus = strtolower($sessionModel->status_session ?? '');

        $totalMatchesCount = 0;
        $completedMatchesCount = 0;
        $drawingRecord = Drawing::where('session_id', $game['id'])->first();
        if ($drawingRecord) {
            $dbMatches = GameMatch::where('drawing_id', $drawingRecord->drawing_id)->get();
            $totalMatchesCount = $dbMatches->count();
            $completedMatchesCount = $dbMatches->whereIn('status_match', ['Completed', 'Stopped'])->count();
        }

        // Cek apakah ada skor yang selesai dicatat
        $hasScores = $completedMatchesCount > 0;
        if (! $hasScores) {
            foreach ($savedScores as $k => $v) {
                if ($k !== '_meta' && is_array($v) && in_array(($v['status'] ?? ''), ['completed', 'stopped'])) {
                    $hasScores = true;
                    $completedMatchesCount++;
                }
            }
        }

        $isFinished = in_array($sessionStatus, ['finished', 'completed']) || ($totalMatchesCount > 0 && $completedMatchesCount >= $totalMatchesCount);

        // Tentukan winner & scoreboard match yang ditampilkan
        $lastRoundKey = null;
        $lastScore = null;
        $drawing = $game['drawing'] ?? [];

        if (! empty($drawing) && $hasScores) {
            $targetRoundKey = null;

            if (! empty($savedScores['_meta']['last_round_key']) && isset($drawing[$savedScores['_meta']['last_round_key']])) {
                $targetRoundKey = $savedScores['_meta']['last_round_key'];
            }

            if (! $targetRoundKey) {
                foreach ($drawing as $rKey => $rData) {
                    if (isset($savedScores[$rKey]) && ($savedScores[$rKey]['status'] ?? '') === 'completed') {
                        $targetRoundKey = $rKey;
                    }
                }
            }

            if (! $targetRoundKey) {
                foreach ($drawing as $rKey => $rData) {
                    if (isset($effectiveScores[$rKey]) && ($effectiveScores[$rKey]['status'] ?? '') === 'completed') {
                        $targetRoundKey = $rKey;
                        break;
                    }
                }
            }

            if ($targetRoundKey) {
                $lastRoundKey = $targetRoundKey;
                $lastRound = $drawing[$lastRoundKey] ?? [];

                if (isset($savedScores[$lastRoundKey]) && ($savedScores[$lastRoundKey]['status'] ?? '') === 'completed') {
                    $lastScore = $savedScores[$lastRoundKey];
                } else {
                    $lastScore = $effectiveScores[$lastRoundKey] ?? null;
                }

                if ($lastScore) {
                    $lastScore['team_a'] = $lastRound['team_a'] ?? [];
                    $lastScore['team_b'] = $lastRound['team_b'] ?? [];
                    $lastScore['round_title'] = ucfirst(str_replace('_', ' ', $lastRoundKey));
                }
            }
        }

        // Data statistik pemain berdasarkan hasil pertandingan yang tersimpan.
        $playerRecap = $this->buildPlayerRecap($game, $rankedPlayers);
        $storyPlayerStats = [];
        foreach ($rankedPlayers as $rankedPlayer) {
            $storyPlayerStats[$rankedPlayer['name']] = $this->buildPlayerRecap($game, $rankedPlayers, $rankedPlayer['name']);
        }

        // Ambil data Kudos yang tersimpan permanen di database tb_kudos
        $kudosList = Kudos::where('session_id', (int) $id)->get();
        $savedKudos = [];
        $currentUserId = Auth::id();
        $userGivenKudos = [];
        foreach ($kudosList as $k) {
            $key = "{$k->recipient_name}:{$k->badge}";
            $savedKudos[$key] = ($savedKudos[$key] ?? 0) + 1;
            if ($currentUserId && $k->giver_user_id === $currentUserId) {
                $userGivenKudos[$key] = true;
            }
        }

        $hasStoppedMatches = false;
        foreach ($effectiveScores as $k => $v) {
            if (is_array($v) && ($v['status'] ?? '') === 'stopped') {
                $hasStoppedMatches = true;
                break;
            }
        }
        if (! $hasStoppedMatches) {
            foreach ($savedScores as $k => $v) {
                if ($k !== '_meta' && is_array($v) && ($v['status'] ?? '') === 'stopped') {
                    $hasStoppedMatches = true;
                    break;
                }
            }
        }

        return view('scoring.recap', compact(
            'game',
            'scoringSystem',
            'rankedPlayers',
            'lastScore',
            'playerRecap',
            'storyPlayerStats',
            'savedScores',
            'effectiveScores',
            'hasScores',
            'isFinished',
            'completedMatchesCount',
            'totalMatchesCount',
            'savedKudos',
            'userGivenKudos',
            'hasStoppedMatches'
        ));
    }

    /**
     * Berikan / Cabut Kudos untuk pemain pada suatu sesi pertandingan (Disimpan permanen ke tb_kudos).
     */
    public function toggleKudos(Request $request)
    {
        if (! Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Silakan login terlebih dahulu untuk memberikan Kudos kepada pemain.',
            ], 401);
        }

        $request->validate([
            'session_id' => 'required|integer',
            'player_name' => 'required|string|max:255',
            'player_id' => 'nullable|integer',
            'badge' => 'required|string|max:100',
        ]);

        $sessionId = $request->integer('session_id');
        $playerName = trim($request->string('player_name')->toString());
        $playerId = $request->input('player_id');
        $badge = trim($request->string('badge')->toString());
        $currentUserId = Auth::id();

        $query = Kudos::where('session_id', $sessionId)
            ->where('recipient_name', $playerName)
            ->where('badge', $badge)
            ->where('giver_user_id', $currentUserId);

        $existing = $query->first();

        if ($existing) {
            $existing->delete();
            $action = 'removed';
            $isActive = false;
        } else {
            if (! $playerId) {
                $playerId = Player::where('nama', $playerName)->value('player_id');
            }

            Kudos::create([
                'session_id' => $sessionId,
                'giver_user_id' => $currentUserId,
                'recipient_player_id' => $playerId,
                'recipient_name' => $playerName,
                'badge' => $badge,
            ]);
            $action = 'added';
            $isActive = true;
        }

        $totalBadgeCount = Kudos::where('session_id', $sessionId)
            ->where('recipient_name', $playerName)
            ->where('badge', $badge)
            ->count();

        return response()->json([
            'success' => true,
            'action' => $action,
            'active' => $isActive,
            'count' => $totalBadgeCount,
            'player_name' => $playerName,
            'badge' => $badge,
            'message' => $isActive ? "Kudos untuk {$playerName} berhasil disimpan!" : "Kudos untuk {$playerName} dicabut.",
        ]);
    }

    private function resolveActualMatchHistory(int $sessionId, array $fallbackDrawing): array
    {
        $drawing = Drawing::where('session_id', $sessionId)->first();
        if (! $drawing) {
            return $fallbackDrawing;
        }

        $session = SessionModel::with('courts')->find($sessionId);
        $courtCount = $session ? max(1, $session->courts->count()) : 1;

        $matches = GameMatch::where('drawing_id', $drawing->drawing_id)
            ->with(['participants.player', 'scores'])
            ->orderBy('nomor_match')
            ->get();

        if ($matches->isEmpty()) {
            return $fallbackDrawing;
        }

        $rounds = [];
        foreach ($matches as $match) {
            $nomorMatch = (int) ($match->nomor_match ?? 1);
            $roundNumber = intdiv(max(0, $nomorMatch - 1), $courtCount) + 1;
            $courtNumber = (($nomorMatch - 1) % $courtCount) + 1;
            $roundKey = "round_{$roundNumber}";

            $teamA = $match->participants
                ->filter(fn ($participant) => strtoupper((string) ($participant->side ?? '')) === 'A')
                ->map(fn ($participant) => $participant->player?->nama ?? '')
                ->filter()
                ->values()
                ->all();

            $teamB = $match->participants
                ->filter(fn ($participant) => strtoupper((string) ($participant->side ?? '')) === 'B')
                ->map(fn ($participant) => $participant->player?->nama ?? '')
                ->filter()
                ->values()
                ->all();

            if (empty($teamA) && $match->participants->isNotEmpty()) {
                $teamA = $match->participants->take((int) ceil($match->participants->count() / 2))->map(fn ($participant) => $participant->player?->nama ?? '')->filter()->values()->all();
            }

            if (empty($teamB) && $match->participants->isNotEmpty()) {
                $teamB = $match->participants->skip((int) ceil($match->participants->count() / 2))->map(fn ($participant) => $participant->player?->nama ?? '')->filter()->values()->all();
            }

            $rounds[$roundKey]['team_a'] ??= $teamA;
            $rounds[$roundKey]['team_b'] ??= $teamB;
            $rounds[$roundKey]['matches'][] = [
                'court' => $courtNumber,
                'court_name' => "Court {$courtNumber}",
                'team_a' => $teamA,
                'team_b' => $teamB,
                'team_a_names' => $teamA,
                'team_b_names' => $teamB,
            ];
        }

        return $rounds ?: $fallbackDrawing;
    }

    private function buildPlayerRecap(array $game, array $rankedPlayers, ?string $requestedPlayerName = null): ?array
    {
        $playerName = null;
        $participant = null;
        $user = Auth::user();

        if ($requestedPlayerName !== null && $requestedPlayerName !== '') {
            $cleanReqName = ScoringService::cleanPlayerName($requestedPlayerName);
            $playerName = $requestedPlayerName;
            $participant = collect($game['participants'] ?? [])->first(function ($p) use ($cleanReqName) {
                return ScoringService::cleanPlayerName($p['name'] ?? '') === $cleanReqName;
            });
        } elseif ($user) {
            // Pengguna sedang login: cari peserta sesi yang terhubung ke akun ini
            $userPlayerIds = Player::where('user_id', $user->user_id)->pluck('player_id')->map('intval')->all();
            $cleanUserName = ScoringService::cleanPlayerName($user->nama ?? '');

            // 1. Cari via session participants ($game['participants'])
            $participant = collect($game['participants'] ?? [])->first(function ($p) use ($user, $userPlayerIds, $cleanUserName) {
                $pUserId = isset($p['user_id']) ? (int) $p['user_id'] : null;
                $pId = isset($p['id']) ? (int) $p['id'] : (isset($p['player_id']) ? (int) $p['player_id'] : null);

                if ($pUserId !== null && $pUserId === (int) $user->user_id) {
                    return true;
                }
                if ($pId !== null && in_array($pId, $userPlayerIds, true)) {
                    return true;
                }

                return ScoringService::cleanPlayerName($p['name'] ?? '') === $cleanUserName;
            });

            // 2. Jika tidak ditemukan di $game['participants'], cek relasi tb_session_player di database
            if (! $participant && ! empty($game['id'])) {
                $sessionPlayer = Player::whereHas('sessions', function ($q) use ($game) {
                    $q->where('tb_session.session_id', (int) $game['id']);
                })->where(function ($q) use ($user, $userPlayerIds) {
                    $q->where('user_id', $user->user_id);
                    if (! empty($userPlayerIds)) {
                        $q->orWhereIn('player_id', $userPlayerIds);
                    }
                })->first();

                if ($sessionPlayer) {
                    $playerName = $sessionPlayer->nama;
                    $cleanSessionPlayerName = ScoringService::cleanPlayerName($playerName);
                    $participant = collect($game['participants'] ?? [])->first(function ($p) use ($cleanSessionPlayerName) {
                        return ScoringService::cleanPlayerName($p['name'] ?? '') === $cleanSessionPlayerName;
                    }) ?: [
                        'id' => $sessionPlayer->player_id,
                        'name' => $sessionPlayer->nama,
                        'level' => $sessionPlayer->level ?? 'Intermediate',
                        'avatar' => $sessionPlayer->foto,
                    ];
                }
            }

            if ($participant) {
                $playerName = $participant['name'] ?? $playerName;
            } else {
                // User login tidak berpartisipasi dalam sesi ini -> jangan tampilkan kartu peserta lain
                return null;
            }
        } else {
            // Pengunjung anonim / guest -> jangan tampilkan kartu peserta lain
            return null;
        }

        if (empty($playerName)) {
            return null;
        }

        $cleanPlayerName = ScoringService::cleanPlayerName($playerName);
        $playerStats = collect($rankedPlayers)->first(function (array $player) use ($cleanPlayerName) {
            return ScoringService::cleanPlayerName($player['name'] ?? '') === $cleanPlayerName;
        });

        if (! $playerStats) {
            return null;
        }

        $durationMinutes = 0;
        try {
            $drawing = Drawing::where('session_id', $game['id'])->first();
            if ($drawing) {
                $matches = GameMatch::with('participants.player')
                    ->where('drawing_id', $drawing->drawing_id)
                    ->where('status_match', 'Completed')
                    ->get();

                $participantId = $participant['id'] ?? null;
                foreach ($matches as $match) {
                    $isParticipant = $participantId
                        ? $match->participants->contains('player_id', (int) $participantId)
                        : $match->participants->contains(function ($matchParticipant) use ($cleanPlayerName) {
                            return ScoringService::cleanPlayerName($matchParticipant->player?->nama ?? '') === $cleanPlayerName;
                        });

                    if (! $isParticipant || ! $match->waktu_mulai || ! $match->waktu_selesai) {
                        continue;
                    }

                    $startedAt = Carbon::parse($match->waktu_mulai);
                    $finishedAt = Carbon::parse($match->waktu_selesai);
                    if ($finishedAt->greaterThan($startedAt)) {
                        $durationMinutes += $startedAt->diffInMinutes($finishedAt);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to calculate player recap duration: '.$e->getMessage());
        }

        $matchesPlayed = (int) ($playerStats['matches'] ?? 0);
        $wins = (int) ($playerStats['wins'] ?? 0);

        return [
            'player_name' => $playerStats['name'] ?? $playerName,
            'avatar' => $participant['avatar'] ?? ($playerStats['avatar'] ?? null),
            'level' => $participant['level'] ?? ($playerStats['level'] ?? 'Intermediate'),
            'total_points' => (int) ($playerStats['points_for'] ?? 0),
            'duration_played' => $durationMinutes > 0 ? round($durationMinutes).'m' : '0m',
            'wins' => $wins,
            'losses' => max(0, $matchesPlayed - $wins),
            'win_rate' => $matchesPlayed > 0 ? round(($wins / $matchesPlayed) * 100).'%' : '0%',
            'is_participant' => true,
        ];
    }

    private function isHostForSession(SessionModel $session): bool
    {
        if (! Auth::check()) {
            return false;
        }

        $user = Auth::user();
        if ($user->role === 'admin') {
            return true;
        }

        $isHostRole = (bool) ($user->is_host ?? false) || ($user->role === 'host');

        return $isHostRole && (int) $user->user_id === (int) $session->host_user_id;
    }

    private function getGameData($id = 1)
    {
        $dbSession = SessionModel::with(['sport', 'venue', 'courts', 'players.user', 'host'])->findOrFail((int) $id);

        if ($dbSession) {
            $participants = $dbSession->players->map(function ($p) {
                return [
                    'id' => $p->player_id,
                    'user_id' => $p->user_id,
                    'name' => $p->nama,
                    'gender' => $p->gender ?? 'Male',
                    'age' => $p->usia,
                    'level' => $p->level ?? 'Intermediate',
                    'is_member' => ! empty($p->user_id),
                    'avatar' => $p->user->foto ?? ($p->foto ?? null),
                ];
            })->toArray();

            // Baca jenis_permainan dari session (Single/Double)
            $sessionJenisPermainan = $dbSession->jenis_permainan ?? 'Double';

            $format = $this->isHostForSession($dbSession) ? strtolower(request('format', '')) : '';
            if (empty($format)) {
                $dbDrawing = Drawing::where('session_id', $dbSession->session_id)->with('matchFormat')->first();
                $format = strtolower($dbDrawing->matchFormat->nama_format ?? 'americano');
            }
            $courtCount = max(1, $dbSession->courts->count());

            // Cek apakah match sudah terkunci
            $isLocked = false;
            if (Cache::get("drawing.locked_{$dbSession->session_id}", false)) {
                $isLocked = true;
            }
            if (isset($dbSession->status_session) && in_array(strtolower($dbSession->status_session), ['in progress', 'in_progress', 'completed', 'finished', 'selesai'])) {
                $isLocked = true;
            }
            $cacheKey = "scoring.game_{$dbSession->session_id}";
            $savedScores = Cache::get($cacheKey, []);
            if (! empty($savedScores) && is_array($savedScores)) {
                foreach ($savedScores as $k => $v) {
                    if ($k !== '_meta' && is_array($v) && (
                        ($v['status'] ?? '') === 'in_progress' ||
                        ($v['status'] ?? '') === 'completed' ||
                        ($v['games_a'] ?? 0) > 0 || ($v['games_b'] ?? 0) > 0 ||
                        ($v['score_a'] ?? 0) > 0 || ($v['score_b'] ?? 0) > 0 ||
                        ($v['sets_a'] ?? 0) > 0 || ($v['sets_b'] ?? 0) > 0
                    )) {
                        $isLocked = true;
                        break;
                    }
                }
            }

            $currentParticipantNames = array_values(array_filter(array_map(function ($p) {
                $name = is_array($p) ? ($p['name'] ?? $p['nama'] ?? '') : (is_object($p) ? ($p->nama ?? $p->name ?? '') : (string) $p);

                return trim($name);
            }, $participants)));
            sort($currentParticipantNames);

            $cachedSchedule = Cache::get("drawing.schedule_{$dbSession->session_id}");
            $isCacheStale = false;
            if ($cachedSchedule && ! empty($cachedSchedule['rounds'])) {
                $cachedPlayers = [];
                foreach ($cachedSchedule['rounds'] as $r) {
                    if (! empty($r['matches'])) {
                        foreach ($r['matches'] as $m) {
                            foreach (($m['team_a_names'] ?? []) as $name) {
                                if (! empty($name)) {
                                    $cachedPlayers[trim($name)] = true;
                                }
                            }
                            foreach (($m['team_b_names'] ?? []) as $name) {
                                if (! empty($name)) {
                                    $cachedPlayers[trim($name)] = true;
                                }
                            }
                        }
                    }
                    foreach (($r['resting'] ?? []) as $name) {
                        if (! empty($name)) {
                            $cachedPlayers[trim($name)] = true;
                        }
                    }
                }
                $cachedPlayerNames = array_keys($cachedPlayers);
                sort($cachedPlayerNames);

                $isTeam = str_contains($format, 'team');
                $isSingle = strtolower($sessionJenisPermainan ?? '') === 'single';
                $scoringSystem = ScoringService::detectScoringSystem($dbSession->scoring_system ?? 'Total of 3');
                $expectedRounds = ScoringService::determineSessionRoundCount(
                    $scoringSystem,
                    count($participants),
                    $isTeam,
                    $isSingle,
                    $dbSession->waktu_session ?? null,
                    $courtCount
                );

                if (! $isLocked && ($currentParticipantNames !== $cachedPlayerNames || ($expectedRounds !== null && count($cachedSchedule['rounds']) !== $expectedRounds))) {
                    $isCacheStale = true;
                }
            }

            if ($cachedSchedule && ! empty($cachedSchedule['rounds']) && ! $isCacheStale) {
                $rounds = $cachedSchedule['rounds'];
                $courtCount = $cachedSchedule['court_count'] ?? $courtCount;
            } else {
                try {
                    $isTeam = str_contains($format, 'team');
                    $isSingle = strtolower($sessionJenisPermainan ?? '') === 'single';
                    $scoringSystem = ScoringService::detectScoringSystem($dbSession->scoring_system ?? 'Total of 3');
                    $roundCount = ScoringService::determineSessionRoundCount(
                        $scoringSystem,
                        count($participants),
                        $isTeam,
                        $isSingle,
                        $dbSession->waktu_session ?? null,
                        $courtCount
                    );

                    if ($isTeam && count($participants) >= 4 && count($participants) % 2 === 0) {
                        $teamService = new TeamAmericanoService;
                        $drawingData = $teamService->generateTeamRounds($participants, $courtCount, null, $roundCount);
                        $rounds = $drawingData['rounds'] ?? [];
                    } else {
                        // Gunakan jenis_permainan dari session agar Single/Double benar
                        $americanoService = new AmericanoService;
                        $rounds = $americanoService->generateRounds($participants, $courtCount, $roundCount, $sessionJenisPermainan ?? 'Double');
                    }
                } catch (\Throwable $e) {
                    $isTeam = str_contains($format, 'team');
                    $isSingle = strtolower($sessionJenisPermainan ?? '') === 'single';
                    $scoringSystem = ScoringService::detectScoringSystem($dbSession->scoring_system ?? 'Total of 3');
                    $roundCount = ScoringService::determineSessionRoundCount(
                        $scoringSystem,
                        count($participants),
                        $isTeam,
                        $isSingle,
                        $dbSession->waktu_session ?? null,
                        $courtCount
                    );
                    $americanoService = new AmericanoService;
                    $rounds = $americanoService->generateRounds($participants, $courtCount, $roundCount, $sessionJenisPermainan ?? 'Double');
                }
            }

            $isSingle = strtolower($sessionJenisPermainan ?? '') === 'single';
            if ($isSingle && count($participants) === 4 && $courtCount === 1) {
                $cacheKey = "scoring.game_{$dbSession->session_id}";
                $savedScores = Cache::get($cacheKey, []);
                $americanoService = new AmericanoService;
                $scoringSystem = ScoringService::detectScoringSystem($dbSession->scoring_system ?? 'Total of 3');
                $rounds = $americanoService->resolveDynamicSingleRounds($rounds, $savedScores, $scoringSystem, (int) $dbSession->session_id);
            }

            $drawingMap = [];
            foreach ($rounds as $rNum => $rData) {
                $roundMatches = $rData['matches'] ?? [];
                if (empty($roundMatches)) {
                    $roundMatches[] = [
                        'court' => 1,
                        'court_name' => 'Court 1',
                        'team_a' => $rData['teamA'] ?? ($rData['team_a'] ?? []),
                        'team_b' => $rData['teamB'] ?? ($rData['team_b'] ?? []),
                        'team_a_names' => $rData['teamA_names'] ?? ($rData['team_a_names'] ?? []),
                        'team_b_names' => $rData['teamB_names'] ?? ($rData['team_b_names'] ?? []),
                    ];
                }

                $drawingMap["round_{$rNum}"] = [
                    'round_number' => $rNum,
                    'team_a' => $rData['teamA'] ?? ($rData['team_a'] ?? []),
                    'team_b' => $rData['teamB'] ?? ($rData['team_b'] ?? []),
                    'team_a_names' => $rData['teamA_names'] ?? ($rData['team_a_names'] ?? []),
                    'team_b_names' => $rData['teamB_names'] ?? ($rData['team_b_names'] ?? []),
                    'resting' => $rData['resting'] ?? [],
                    'matches' => $roundMatches,
                    'court_count' => $courtCount,
                ];
            }

            return [
                'id' => $dbSession->session_id,
                'title' => $dbSession->nama_session,
                'sport' => $dbSession->sport->nama_sport ?? 'Padel',
                'venue_id' => $dbSession->venue_id,
                'venue_name' => $dbSession->venue->nama_venue ?? 'Arena Olahraga',
                'court_name' => GameController::resolveSessionCourtDisplay($dbSession->courts),
                'date' => $dbSession->datetime ? $dbSession->datetime->format('Y-m-d') : date('Y-m-d'),
                'time' => GameController::resolveSessionDisplayTime($dbSession),
                'duration' => GameController::resolveSessionDuration($dbSession),
                'quota' => (int) ($dbSession->jumlah_pemain ?? count($participants)),
                'joined_count' => count($participants),
                'status' => 'In Progress',
                'level_recommendation' => 'All Level Welcome',
                'match_format' => $format,
                'jenis_permainan' => $sessionJenisPermainan ?? 'Double',
                'scoring_system' => $dbSession->scoring_system ?? 'Total of 3',
                'host' => [
                    'name' => $dbSession->host->nama ?? 'Host Matcha',
                    'role' => 'Host Game',
                    'level' => 'Intermediate',
                    'avatar' => $dbSession->host->foto ?? null,
                ],
                'participants' => $participants,
                'drawing' => $drawingMap,
            ];
        }

        abort(404);
    }

    /**
     * Cek apakah seluruh match dan court pada round tertentu sudah terminal (status === 'completed' atau 'stopped').
     * Memvalidasi data dari cache dan database secara komprehensif.
     */
    private function isRoundFullyCompleted(array $game, string $roundKey, array $savedScores): bool
    {
        $round = $game['drawing'][$roundKey] ?? [];
        $matches = $round['matches'] ?? [];
        $matchCount = count($matches);

        // Jika matches kosong di drawing data, periksa fallback single-court
        if ($matchCount === 0) {
            $score = $savedScores[$roundKey] ?? ($savedScores["{$roundKey}_court_1"] ?? null);
            if (! $score || ! ScoringService::isMatchTerminal($score['status'] ?? '')) {
                $dbScore = $this->getScoreFromDatabase($game['id'], $roundKey, 0);
                if ($dbScore && ScoringService::isMatchTerminal($dbScore['status'] ?? '')) {
                    $score = $dbScore;
                }
            }

            return ScoringService::isMatchTerminal($score['status'] ?? '');
        }

        foreach ($matches as $matchIndex => $match) {
            $matchKey = $matchCount > 1
                ? "{$roundKey}_court_".($matchIndex + 1)
                : $roundKey;
            $score = $savedScores[$matchKey] ?? null;

            if (! $score && $matchCount === 1 && isset($savedScores["{$roundKey}_court_1"])) {
                $score = $savedScores["{$roundKey}_court_1"];
            }

            // Cross-check ke database jika score belum ada di cache atau statusnya belum terminal
            if (! $score || ! ScoringService::isMatchTerminal($score['status'] ?? '')) {
                $dbScore = $this->getScoreFromDatabase($game['id'], $roundKey, $matchIndex);
                if ($dbScore && ScoringService::isMatchTerminal($dbScore['status'] ?? '')) {
                    $score = $dbScore;
                }
            }

            if (! ScoringService::isMatchTerminal($score['status'] ?? '')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine which scoring rounds are unlocked in sequence.
     */
    private function getRoundAccess(array $game, array $scoringSystem, array $savedScores): array
    {
        $drawingRounds = ! empty($game['drawing']) ? array_keys($game['drawing']) : [];
        if (empty($drawingRounds) && ! empty($game['id'])) {
            $cachedSchedule = Cache::get("drawing.schedule_{$game['id']}", []);
            if (! empty($cachedSchedule['rounds'])) {
                $drawingRounds = array_map(fn ($k) => str_starts_with((string) $k, 'round_') ? (string) $k : "round_{$k}", array_keys($cachedSchedule['rounds']));
            }
        }

        if (! empty($drawingRounds)) {
            $roundKeys = $drawingRounds;
        } elseif ($scoringSystem['is_sets']) {
            $numSets = (int) ($scoringSystem['max_sets'] ?? 1);
            $roundKeys = array_map(fn ($roundNumber) => "round_{$roundNumber}", range(1, max(1, $numSets)));
        } else {
            $roundKeys = array_map(fn ($roundNumber) => "round_{$roundNumber}", range(1, 10));
        }
        $roundAccess = [];
        $previousRoundCompleted = true;

        foreach ($roundKeys as $roundKey) {
            $roundAccess[$roundKey] = $previousRoundCompleted;
            if (! $previousRoundCompleted) {
                continue;
            }

            $previousRoundCompleted = $this->isRoundFullyCompleted($game, $roundKey, $savedScores);
        }

        return $roundAccess;
    }

    /**
     * Membentuk canonical match state awal atau memulihkan dari database jika cache kosong.
     * Menggunakan sumber kebenaran database dan aturan scoring system aplikasi.
     * State default hanya digunakan jika pertandingan memang belum memiliki skor di DB.
     */
    public function getDefaultMatchState(array $game, string $matchKey, string $round, array $scoringSystem): array
    {
        $gameId = (int) ($game['id'] ?? 0);
        $courtIndex = 0;
        if (preg_match('/court_(\d+)/i', $matchKey, $mC)) {
            $courtIndex = max(0, ((int) $mC[1]) - 1);
        }

        // 1. Coba ambil skor yang sah dari database terlebih dahulu (lempar exception jika DB bermasalah)
        $dbScore = $this->getScoreFromDatabase($gameId, $round, $courtIndex, true);
        if (! empty($dbScore)) {
            return $dbScore;
        }

        // 2. Jika database berhasil dibaca dan memang belum memiliki skor untuk match ini,
        // cari match record untuk mendapatkan nomor match, ID, dan version yang sinkron
        $match = null;
        try {
            $session = SessionModel::find($gameId);
            if ($session) {
                $match = ScoringService::ensureMatchAndParticipants($session, $round, $courtIndex);
            }
        } catch (\Throwable $e) {
            Log::warning("[getDefaultMatchState] Could not ensure match record: {$e->getMessage()}");
        }

        $nowMs = (int) round(microtime(true) * 1000);
        $scoringType = $scoringSystem['type'] ?? ($scoringSystem['is_total_of'] ?? false ? 'total_of_sets' : 'first_to_games');

        return [
            'match_id' => $match ? (int) $match->match_id : null,
            'version' => $match ? (int) ($match->version ?? 1) : 1,
            'server_version' => $match ? (int) ($match->version ?? 1) : 1,
            'updated_at_ms' => $nowMs,
            'score_a' => 0,
            'score_b' => 0,
            'games_a' => 0,
            'games_b' => 0,
            'point_display_a' => '0',
            'point_display_b' => '0',
            'set_number' => 1,
            'sets_a' => 0,
            'sets_b' => 0,
            'set_history' => [],
            'idx_a' => 0,
            'idx_b' => 0,
            'is_deuce' => false,
            'advantage' => null,
            'scoring_type' => $scoringType,
            'status' => 'in_progress',
            'winner_team' => null,
            'stop_type' => null,
            'stop_reason' => null,
            'stopped_by_user_id' => null,
            'last_event_id' => $match ? $match->last_event_id : null,
        ];
    }

    /**
     * Ambil skor dari database (tb_match & tb_score) untuk round & court tertentu.
     */
    protected function getScoreFromDatabase($gameId, $round, $courtIndex = 0, bool $throwOnFailure = false)
    {
        try {
            $drawing = Drawing::where('session_id', (int) $gameId)->first();
            if (! $drawing) {
                return null;
            }

            $session = SessionModel::with('courts')->find((int) $gameId);
            $courtCount = $session ? max(1, $session->courts->count()) : 1;
            preg_match('/(\d+)/', $round, $rMatch);
            $rNum = isset($rMatch[1]) ? (int) $rMatch[1] : 1;
            $nomorMatch = ($rNum - 1) * $courtCount + ($courtIndex + 1);

            $match = GameMatch::where('drawing_id', $drawing->drawing_id)
                ->where('nomor_match', $nomorMatch)
                ->first();

            if (! $match) {
                return null;
            }

            $scores = Score::where('match_id', $match->match_id)
                ->orderBy('set_number', 'asc')
                ->orderBy('version', 'desc')
                ->orderBy('score_id', 'desc')
                ->get();

            $activeScores = $scores->filter(fn ($sc) => strtolower((string) ($sc->status_score ?? '')) !== 'eventlog');
            $isCompleted = strtolower((string) ($match->status_match ?? '')) === 'completed';
            $isStopped = strtolower((string) ($match->status_match ?? '')) === 'stopped';
            if ($activeScores->isEmpty() && ! $isCompleted && ! $isStopped) {
                return null;
            }

            if ($isCompleted || $isStopped) {
                $officialScore = $activeScores
                    ->filter(fn ($sc) => in_array(strtolower((string) ($sc->status_score ?? '')), ['final', 'completed', 'stopped'], true))
                    ->sortByDesc('version')
                    ->sortByDesc('score_id')
                    ->first();
                $lastScore = $officialScore ?: $activeScores->sortByDesc('version')->sortByDesc('score_id')->first();
            } else {
                $lastScore = $activeScores->sortByDesc('version')->sortByDesc('score_id')->first();
            }
            $setHistory = [];
            $setsA = 0;
            $setsB = 0;
            $gamesA = 0;
            $gamesB = 0;

            $groupedBySet = $activeScores->groupBy('set_number');
            foreach ($groupedBySet as $setNum => $setScores) {
                $latestSetScore = $setScores->sortByDesc('version')->sortByDesc('score_id')->first();
                $setsA = max($setsA, (int) $latestSetScore->set_score_a);
                $setsB = max($setsB, (int) $latestSetScore->set_score_b);
                $setHistory[] = [
                    'set' => (int) $setNum,
                    'score_a' => (int) ($latestSetScore->game_score_a ?? $latestSetScore->score_side_a ?? 0),
                    'score_b' => (int) ($latestSetScore->game_score_b ?? $latestSetScore->score_side_b ?? 0),
                ];
            }

            $persistentEventIds = $scores->pluck('last_event_id')->filter()->unique()->values()->all();
            if ($match->last_event_id && ! in_array($match->last_event_id, $persistentEventIds, true)) {
                $persistentEventIds[] = (string) $match->last_event_id;
            }

            if ($lastScore) {
                $gamesA = (int) ($lastScore->game_score_a ?? $lastScore->score_side_a ?? 0);
                $gamesB = (int) ($lastScore->game_score_b ?? $lastScore->score_side_b ?? 0);
                $setsA = max($setsA, (int) $lastScore->set_score_a);
                $setsB = max($setsB, (int) $lastScore->set_score_b);
            }

            $status = $isStopped ? 'stopped' : ($isCompleted ? 'completed' : 'in_progress');

            $tennisPoints = ['0', '15', '30', '40'];
            $ptA = (string) ($lastScore->point_score_a ?? '0');
            $ptB = (string) ($lastScore->point_score_b ?? '0');
            $idxA = array_search($ptA, $tennisPoints);
            $idxA = ($idxA !== false) ? (int) $idxA : 0;
            $idxB = array_search($ptB, $tennisPoints);
            $idxB = ($idxB !== false) ? (int) $idxB : 0;
            $isDeuce = ($ptA === '40' && $ptB === '40') || ($ptA === 'ADV' || $ptB === 'ADV');
            $advantage = ($ptA === 'ADV') ? 'A' : (($ptB === 'ADV') ? 'B' : null);
            if ($isDeuce) {
                $idxA = 3;
                $idxB = 3;
            }

            return [
                'match_id' => $match->match_id,
                'version' => (int) ($lastScore->version ?? ($match->version ?? 1)),
                'updated_at_ms' => $match->updated_at ? (int) round($match->updated_at->timestamp * 1000) : (int) round(microtime(true) * 1000),
                'score_a' => $gamesA,
                'score_b' => $gamesB,
                'point_display_a' => $ptA,
                'point_display_b' => $ptB,
                'set_number' => (int) ($lastScore->set_number ?? 1),
                'sets_a' => $setsA,
                'sets_b' => $setsB,
                'games_a' => $gamesA,
                'games_b' => $gamesB,
                'set_history' => $setHistory,
                'idx_a' => $idxA,
                'idx_b' => $idxB,
                'is_deuce' => $isDeuce,
                'advantage' => $advantage,
                'scoring_type' => $lastScore->scoring_system ?? 'total_of_sets',
                'status' => $status,
                'winner_team' => $isStopped ? null : $match->winner_team,
                'stop_type' => $match->stop_type ?? null,
                'stop_reason' => $match->stop_reason ?? null,
                'stopped_by_user_id' => $match->stopped_by_user_id ?? null,
                'stopped_at' => $match->stopped_at ? (is_string($match->stopped_at) ? $match->stopped_at : $match->stopped_at->toDateTimeString()) : null,
                'withdrawn_player_ids' => ! empty($match->withdrawn_player_ids) ? (is_array($match->withdrawn_player_ids) ? $match->withdrawn_player_ids : json_decode($match->withdrawn_player_ids, true)) : [],
                'player_notes' => ! empty($match->player_notes) ? (is_array($match->player_notes) ? $match->player_notes : json_decode($match->player_notes, true)) : [],
                'last_event_id' => (string) ($lastScore->last_event_id ?? ($match->last_event_id ?? '')),
                'persistent_event_ids' => $persistentEventIds,
            ];
        } catch (\Throwable $e) {
            Log::warning('Failed to get score from database: '.$e->getMessage());
            if ($throwOnFailure) {
                throw $e;
            }

            return null;
        }
    }

    protected function getOfficialCompletedScoreFromDatabase($gameId, $round, $courtIndex = 0): ?array
    {
        $drawing = DB::table('tb_drawing')->where('session_id', (int) $gameId)->first();
        if (! $drawing) {
            return null;
        }

        $session = SessionModel::with('courts')->find((int) $gameId);
        $courtCount = $session ? max(1, $session->courts->count()) : 1;
        preg_match('/(\d+)/', $round, $rMatch);
        $rNum = isset($rMatch[1]) ? (int) $rMatch[1] : 1;
        $nomorMatch = ($rNum - 1) * $courtCount + ($courtIndex + 1);

        $match = DB::table('tb_match')
            ->where('drawing_id', $drawing->drawing_id)
            ->where('nomor_match', $nomorMatch)
            ->first();

        if (! $match) {
            return null;
        }

        $row = DB::table('tb_score')
            ->where('match_id', $match->match_id)
            ->where(function ($query) {
                $query->where('status_score', 'Final')->orWhere('status_score', 'Completed');
            })
            ->orderByDesc('version')
            ->orderByDesc('score_id')
            ->first();

        if (! $row && ! ScoringService::isMatchTerminal($match->status_match ?? '')) {
            return null;
        }

        $row = $row ?: DB::table('tb_score')->where('match_id', $match->match_id)->orderByDesc('version')->orderByDesc('score_id')->first();
        if (! $row && ! ScoringService::isMatchTerminal($match->status_match ?? '')) {
            return null;
        }

        $isStopped = strtolower((string) ($match->status_match ?? '')) === 'stopped';
        $status = $isStopped ? 'stopped' : (strtolower((string) ($match->status_match ?? '')) === 'completed' || in_array(strtolower((string) ($row->status_score ?? '')), ['final', 'completed'], true) ? 'completed' : 'in_progress');

        return [
            'match_id' => (int) $match->match_id,
            'version' => (int) ($row->version ?? 1),
            'updated_at_ms' => (int) round((strtotime((string) ($match->updated_at ?? now()->toDateTimeString())) * 1000)),
            'score_a' => (int) ($row->game_score_a ?? $row->score_side_a ?? $row->set_score_a ?? 0),
            'score_b' => (int) ($row->game_score_b ?? $row->score_side_b ?? $row->set_score_b ?? 0),
            'point_display_a' => (string) ($row->point_score_a ?? '0'),
            'point_display_b' => (string) ($row->point_score_b ?? '0'),
            'set_number' => (int) ($row->set_number ?? 1),
            'sets_a' => (int) ($row->set_score_a ?? 0),
            'sets_b' => (int) ($row->set_score_b ?? 0),
            'games_a' => (int) ($row->game_score_a ?? $row->score_side_a ?? 0),
            'games_b' => (int) ($row->game_score_b ?? $row->score_side_b ?? 0),
            'set_history' => [[
                'set' => (int) ($row->set_number ?? 1),
                'score_a' => (int) ($row->game_score_a ?? $row->score_side_a ?? 0),
                'score_b' => (int) ($row->game_score_b ?? $row->score_side_b ?? 0),
            ]],
            'idx_a' => 0,
            'idx_b' => 0,
            'is_deuce' => false,
            'advantage' => null,
            'scoring_type' => (string) ($row->scoring_system ?? 'total_of_sets'),
            'status' => $status,
            'winner_team' => $isStopped ? null : ($match->winner_team ?? null),
            'stop_type' => $match->stop_type ?? null,
            'stop_reason' => $match->stop_reason ?? null,
            'stopped_by_user_id' => $match->stopped_by_user_id ?? null,
            'stopped_at' => isset($match->stopped_at) && $match->stopped_at ? (is_string($match->stopped_at) ? $match->stopped_at : (method_exists($match->stopped_at, 'toDateTimeString') ? $match->stopped_at->toDateTimeString() : (string) $match->stopped_at)) : null,
            'withdrawn_player_ids' => ! empty($match->withdrawn_player_ids) ? (is_array($match->withdrawn_player_ids) ? $match->withdrawn_player_ids : json_decode($match->withdrawn_player_ids, true)) : [],
            'player_notes' => ! empty($match->player_notes) ? (is_array($match->player_notes) ? $match->player_notes : json_decode($match->player_notes, true)) : [],
            'last_event_id' => (string) ($row->last_event_id ?? ($match->last_event_id ?? '')),
        ];
    }

    /**
     * Terapkan mutasi 1 poin ('A' atau 'B') ke state match terkini (3-Way Delta Merge).
     */
    protected function applyPointDeltaToState(array $currentState, string $team, array $scoringSystem): array
    {
        $gamesA = (int) ($currentState['games_a'] ?? ($currentState['score_a'] ?? 0));
        $gamesB = (int) ($currentState['games_b'] ?? ($currentState['score_b'] ?? 0));

        // Jika match sudah selesai atau sudah memenuhi batas game, tambahan skor/point delta diabaikan
        if (($currentState['status'] ?? '') === 'completed' || ScoringService::isMatchCompleted($gamesA, $gamesB, $scoringSystem)) {
            return $currentState;
        }

        $tennisPoints = ['0', '15', '30', '40'];
        $idxA = (int) ($currentState['idx_a'] ?? 0);
        $idxB = (int) ($currentState['idx_b'] ?? 0);
        $isDeuce = (bool) ($currentState['is_deuce'] ?? false);
        $advantage = $currentState['advantage'] ?? null;
        $setsA = (int) ($currentState['sets_a'] ?? 0);
        $setsB = (int) ($currentState['sets_b'] ?? 0);
        $status = $currentState['status'] ?? 'in_progress';
        $winnerTeam = $currentState['winner_team'] ?? null;

        $isSets = (bool) ($scoringSystem['is_sets'] ?? true);
        $targetGames = (int) ($scoringSystem['target_games'] ?? 6);

        // Mutasi poin dengan tennis ladder (0 -> 15 -> 30 -> 40 -> Game Win)
        if ($team === 'A') {
            if ($isDeuce) {
                if ($advantage === 'A') {
                    // Game won by Team A
                    $gamesA++;
                    $idxA = 0;
                    $idxB = 0;
                    $isDeuce = false;
                    $advantage = null;
                } elseif ($advantage === 'B') {
                    // Kembali ke Deuce
                    $advantage = null;
                } else {
                    $advantage = 'A';
                }
            } else {
                if ($idxA < 3) {
                    $idxA++;
                    if ($idxA === 3 && $idxB === 3) {
                        $isDeuce = true;
                        $advantage = null;
                    }
                } elseif ($idxA === 3 && $idxB < 3) {
                    // Game won by Team A
                    $gamesA++;
                    $idxA = 0;
                    $idxB = 0;
                    $isDeuce = false;
                    $advantage = null;
                }
            }
        } elseif ($team === 'B') {
            if ($isDeuce) {
                if ($advantage === 'B') {
                    // Game won by Team B
                    $gamesB++;
                    $idxA = 0;
                    $idxB = 0;
                    $isDeuce = false;
                    $advantage = null;
                } elseif ($advantage === 'A') {
                    // Kembali ke Deuce
                    $advantage = null;
                } else {
                    $advantage = 'B';
                }
            } else {
                if ($idxB < 3) {
                    $idxB++;
                    if ($idxB === 3 && $idxA === 3) {
                        $isDeuce = true;
                        $advantage = null;
                    }
                } elseif ($idxB === 3 && $idxA < 3) {
                    // Game won by Team B
                    $gamesB++;
                    $idxA = 0;
                    $idxB = 0;
                    $isDeuce = false;
                    $advantage = null;
                }
            }
        }

        // Resolusi point displays
        if ($isDeuce) {
            $pointDisplayA = ($advantage === 'A') ? 'ADV' : '40';
            $pointDisplayB = ($advantage === 'B') ? 'ADV' : '40';
        } else {
            $pointDisplayA = $tennisPoints[$idxA] ?? '0';
            $pointDisplayB = $tennisPoints[$idxB] ?? '0';
        }

        // Cek apakah set/match selesai
        $isCompleted = ScoringService::isMatchCompleted($gamesA, $gamesB, $scoringSystem);
        if ($isCompleted) {
            $status = 'completed';
            $winnerTeam = ScoringService::determineMatchWinner($gamesA, $gamesB, $scoringSystem);
            $setsA = ($winnerTeam === 'Team A') ? 1 : 0;
            $setsB = ($winnerTeam === 'Team B') ? 1 : 0;
        } else {
            $status = 'in_progress';
            $winnerTeam = null;
            $setsA = 0;
            $setsB = 0;
        }

        return array_merge($currentState, [
            'score_a' => $gamesA,
            'score_b' => $gamesB,
            'games_a' => $gamesA,
            'games_b' => $gamesB,
            'point_display_a' => $pointDisplayA,
            'point_display_b' => $pointDisplayB,
            'idx_a' => $idxA,
            'idx_b' => $idxB,
            'is_deuce' => $isDeuce,
            'advantage' => $advantage,
            'sets_a' => $setsA,
            'sets_b' => $setsB,
            'status' => $status,
            'winner_team' => $winnerTeam,
            'set_history' => [
                ['set' => (int) ($currentState['set_number'] ?? 1), 'score_a' => $gamesA, 'score_b' => $gamesB],
            ],
        ]);
    }

    /**
     * Tandai state match sebagai completed secara konsisten di server.
     */
    protected function applyCompletionToState(array $currentState, array $scoringSystem, ?string $explicitWinner = null, ?int $incomingGamesA = null, ?int $incomingGamesB = null): array
    {
        $gamesA = $incomingGamesA !== null ? $incomingGamesA : (int) ($currentState['games_a'] ?? ($currentState['score_a'] ?? 0));
        $gamesB = $incomingGamesB !== null ? $incomingGamesB : (int) ($currentState['games_b'] ?? ($currentState['score_b'] ?? 0));

        if ($explicitWinner === 'A') {
            $explicitWinner = 'Team A';
        }
        if ($explicitWinner === 'B') {
            $explicitWinner = 'Team B';
        }

        $winner = $explicitWinner ?: ScoringService::determineMatchWinner($gamesA, $gamesB, $scoringSystem);
        $setsA = ($winner === 'Team A') ? 1 : 0;
        $setsB = ($winner === 'Team B') ? 1 : 0;

        return array_merge($currentState, [
            'status' => 'completed',
            'winner_team' => $winner,
            'sets_a' => $setsA,
            'sets_b' => $setsB,
            'point_display_a' => 'Game',
            'point_display_b' => '0',
            'set_history' => [
                ['set' => (int) ($currentState['set_number'] ?? 1), 'score_a' => $gamesA, 'score_b' => $gamesB],
            ],
        ]);
    }

    /**
     * Broadcast state terbaru ke Supabase Realtime Channel.
     */
    protected function broadcastScoreUpdateRealtime(int $gameId, string $matchKey, array $scorePayload): void
    {
        try {
            $url = rtrim(config('services.supabase.url', ''), '/').'/realtime/v1/api/broadcast';
            $key = config('services.supabase.service_key', config('services.supabase.key'));
            if (empty($url) || empty($key)) {
                return;
            }

            $cacheKey = "scoring.game_{$gameId}";
            $savedScores = Cache::get($cacheKey, []);
            $sessionActiveRound = $savedScores['_meta']['active_round'] ?? 'round_1';
            $sessionStatus = $savedScores['_meta']['status'] ?? 'in_progress';

            $headers = [
                'apikey' => $key,
                'Content-Type' => 'application/json',
            ];
            if (str_starts_with($key, 'ey')) {
                $headers['Authorization'] = 'Bearer '.$key;
            }

            $response = Http::withOptions(['verify' => $this->resolveCaBundle()])
                ->withHeaders($headers)
                ->timeout(2)
                ->post($url, [
                    'messages' => [
                        [
                            'topic' => "session_{$gameId}",
                            'event' => 'score_update',
                            'payload' => [
                                'session_id' => $gameId,
                                'match_key' => $matchKey,
                                'session_active_round' => (string) $sessionActiveRound,
                                'session_status' => (string) $sessionStatus,
                                'is_session_finished' => ($sessionStatus === 'finished'),
                                'score_a' => (int) ($scorePayload['games_a'] ?? 0),
                                'score_b' => (int) ($scorePayload['games_b'] ?? 0),
                                'games_a' => (int) ($scorePayload['games_a'] ?? 0),
                                'games_b' => (int) ($scorePayload['games_b'] ?? 0),
                                'point_display_a' => (string) ($scorePayload['point_display_a'] ?? '0'),
                                'point_display_b' => (string) ($scorePayload['point_display_b'] ?? '0'),
                                'server_version' => (int) ($scorePayload['version'] ?? 0),
                                'version' => (int) ($scorePayload['version'] ?? 0),
                                'last_event_id' => (string) ($scorePayload['last_event_id'] ?? ''),
                                'status' => $scorePayload['status'] ?? 'in_progress',
                                'winner_team' => $scorePayload['winner_team'] ?? null,
                                'saved' => $scorePayload,
                            ],
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning("Supabase realtime broadcast response HTTP {$response->status()}");
            }
        } catch (\Throwable $e) {
            Log::warning("Supabase realtime broadcast failed: {$e->getMessage()}");
        }
    }

    /**
     * Broadcast perpindahan ronde / set ke Supabase Realtime Channel.
     */
    protected function broadcastRoundAdvancedRealtime(int $gameId, string $currentRound, string $nextRound): void
    {
        try {
            $url = rtrim(config('services.supabase.url', ''), '/').'/realtime/v1/api/broadcast';
            $key = config('services.supabase.service_key', config('services.supabase.key'));
            if (empty($url) || empty($key)) {
                return;
            }

            $headers = [
                'apikey' => $key,
                'Content-Type' => 'application/json',
            ];
            if (str_starts_with($key, 'ey')) {
                $headers['Authorization'] = 'Bearer '.$key;
            }

            $response = Http::withOptions(['verify' => $this->resolveCaBundle()])
                ->withHeaders($headers)
                ->timeout(2)
                ->post($url, [
                    'messages' => [
                        [
                            'topic' => "session_{$gameId}",
                            'event' => 'round_advanced',
                            'payload' => [
                                'session_id' => $gameId,
                                'previous_round' => $currentRound,
                                'next_round' => $nextRound,
                                'active_round' => $nextRound,
                                'drawing_url' => route('games.drawing', ['id' => $gameId, 'round' => (int) preg_replace('/[^0-9]/', '', $nextRound)]),
                                'timestamp' => now()->toIso8601String(),
                            ],
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning("Supabase realtime round advanced broadcast response HTTP {$response->status()}");
            }
        } catch (\Throwable $e) {
            Log::warning("Supabase realtime round advanced broadcast failed: {$e->getMessage()}");
        }
    }

    /**
     * Broadcast pembukaan scoring live ronde tertentu ke Supabase Realtime Channel.
     */
    protected function broadcastScoringOpenedRealtime(int $gameId, string $round): void
    {
        try {
            $url = rtrim(config('services.supabase.url', ''), '/').'/realtime/v1/api/broadcast';
            $key = config('services.supabase.service_key', config('services.supabase.key'));
            if (empty($url) || empty($key)) {
                return;
            }

            $headers = [
                'apikey' => $key,
                'Content-Type' => 'application/json',
            ];
            if (str_starts_with($key, 'ey')) {
                $headers['Authorization'] = 'Bearer '.$key;
            }

            $roundNum = (int) preg_replace('/[^0-9]/', '', $round);

            $response = Http::withOptions(['verify' => $this->resolveCaBundle()])
                ->withHeaders($headers)
                ->timeout(2)
                ->post($url, [
                    'messages' => [
                        [
                            'topic' => "session_{$gameId}",
                            'event' => 'scoring_opened',
                            'payload' => [
                                'session_id' => $gameId,
                                'round' => $round,
                                'round_num' => $roundNum,
                                'redirect_url' => route('scoring.live', [
                                    'id' => $gameId,
                                    'round' => $round,
                                ]),
                                'timestamp' => now()->toIso8601String(),
                            ],
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning("Supabase realtime scoring opened broadcast response HTTP {$response->status()}");
            }
        } catch (\Throwable $e) {
            Log::warning("Supabase realtime scoring opened broadcast failed: {$e->getMessage()}");
        }
    }

    /**
     * Broadcast penyelesaian seluruh sesi pertandingan ke Supabase Realtime Channel.
     */
    protected function broadcastSessionFinishedRealtime(int $gameId, string $recapUrl): void
    {
        try {
            $url = rtrim(config('services.supabase.url', ''), '/').'/realtime/v1/api/broadcast';
            $key = config('services.supabase.service_key', config('services.supabase.key'));
            if (empty($url) || empty($key)) {
                return;
            }

            $headers = [
                'apikey' => $key,
                'Content-Type' => 'application/json',
            ];
            if (str_starts_with($key, 'ey')) {
                $headers['Authorization'] = 'Bearer '.$key;
            }

            $response = Http::withOptions(['verify' => $this->resolveCaBundle()])
                ->withHeaders($headers)
                ->timeout(2)
                ->post($url, [
                    'messages' => [
                        [
                            'topic' => "session_{$gameId}",
                            'event' => 'session_finished',
                            'payload' => [
                                'session_id' => $gameId,
                                'status' => 'finished',
                                'status_session' => 'Finished',
                                'is_session_finished' => true,
                                'recap_url' => $recapUrl,
                                'timestamp' => now()->toIso8601String(),
                            ],
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning("Supabase realtime session finished broadcast response HTTP {$response->status()}");
            }
        } catch (\Throwable $e) {
            Log::warning("Supabase realtime session finished broadcast failed: {$e->getMessage()}");
        }
    }

    /**
     * Cari ronde yang sedang aktif dari database jika cache metadata belum ada.
     * Mengelompokkan pertandingan per ronde dan memverifikasi kelengkapan seluruh court secara berurutan.
     */
    protected function resolveActiveRoundFromDatabase(int $gameId): string
    {
        try {
            $session = SessionModel::with('courts')->find($gameId);
            if (! $session) {
                return 'round_1';
            }

            // Jika skema database memiliki kolom active_round dan sudah terisi, utamakan nilai persisten ini
            if (Schema::hasColumn('tb_session', 'active_round')) {
                $persistedActiveRound = DB::table('tb_session')->where('session_id', $gameId)->value('active_round') ?? $session->active_round;
                if (! empty($persistedActiveRound)) {
                    return (string) $persistedActiveRound;
                }
            }

            $drawing = Drawing::where('session_id', $gameId)->first();
            if (! $drawing) {
                return 'round_1';
            }

            $courtCount = max(1, $session->courts->count());
            $matches = GameMatch::where('drawing_id', $drawing->drawing_id)
                ->orderBy('nomor_match', 'asc')
                ->get()
                ->keyBy('nomor_match');

            if ($matches->isEmpty()) {
                return 'round_1';
            }

            // Dapatkan jumlah ronde yang valid dari drawing atau cache schedule
            $gameData = $this->getGameData($gameId);
            $drawingRounds = $gameData['drawing'] ?? [];
            if (empty($drawingRounds)) {
                $cachedSchedule = Cache::get("drawing.schedule_{$gameId}");
                $drawingRounds = $cachedSchedule['rounds'] ?? [];
            }

            $totalRounds = count($drawingRounds);
            if ($totalRounds === 0) {
                $maxNomor = (int) $matches->keys()->max();
                $totalRounds = max(1, intdiv(max(0, $maxNomor - 1), $courtCount) + 1);
            }

            // Fallback ketika kolom active_round belum tersedia di tb_session:
            // Periksa ronde secara berurutan.
            // 1. Jika ada ronde yang memiliki match dan belum selesai di seluruh court, ronde tersebut adalah ronde aktif.
            // 2. Jika seluruh court pada ronde r sudah selesai, tetapi ronde r+1 belum memiliki match tercatat di DB:
            //    Pertahankan ronde r agar antarmuka tetap menampilkan ronde r dan tombol lanjut ronde berikutnya tersedia.
            $lastCompletedRound = 0;

            for ($r = 1; $r <= $totalRounds; $r++) {
                $matchesInThisRound = [];
                if (! empty($drawingRounds)) {
                    $rData = $drawingRounds["round_{$r}"] ?? ($drawingRounds[$r] ?? ($drawingRounds[$r - 1] ?? null));
                    $matchesInThisRound = $rData['matches'] ?? [];
                }
                $roundMatchCount = max(1, count($matchesInThisRound) > 0 ? count($matchesInThisRound) : $courtCount);

                $roundAllTerminal = true;
                $hasRecordedMatch = false;

                for ($mIdx = 0; $mIdx < $roundMatchCount; $mIdx++) {
                    $nomorMatch = ($r - 1) * $courtCount + ($mIdx + 1);
                    $dbMatch = $matches->get($nomorMatch);

                    if ($dbMatch) {
                        $hasRecordedMatch = true;
                        if (! ScoringService::isMatchTerminal($dbMatch->status_match ?? '')) {
                            $roundAllTerminal = false;
                        }
                    } else {
                        // Jika match belum terdaftar di database, ronde ini belum terminal
                        $roundAllTerminal = false;
                    }
                }

                if ($hasRecordedMatch && ! $roundAllTerminal) {
                    return "round_{$r}";
                }

                if ($hasRecordedMatch && $roundAllTerminal) {
                    $lastCompletedRound = $r;
                } else {
                    // Belum ada match tercatat untuk ronde ini
                    if ($lastCompletedRound > 0) {
                        return "round_{$lastCompletedRound}";
                    }

                    return "round_{$r}";
                }
            }

            if ($lastCompletedRound > 0) {
                return "round_{$lastCompletedRound}";
            }

            return 'round_1';
        } catch (\Throwable $e) {
            return 'round_1';
        }
    }

    /**
     * Cari berkas bundle CA cert lokal untuk verifikasi SSL aman.
     */
    protected function resolveCaBundle(): string|bool
    {
        $candidates = [
            ini_get('curl.cainfo'),
            ini_get('openssl.cafile'),
            getenv('CURL_CA_BUNDLE'),
            'C:\\xampp\\apache\\bin\\curl-ca-bundle.crt',
            'C:\\php\\extras\\ssl\\cacert.pem',
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && is_file($candidate)) {
                return $candidate;
            }
        }

        return true;
    }
}
