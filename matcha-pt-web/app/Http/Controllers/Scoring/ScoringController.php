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
            $dbCompleted = $dbScore && ($dbScore['status'] ?? '') === 'completed';
            $cachedCompleted = $cachedScore && ($cachedScore['status'] ?? '') === 'completed';

            $cachedTotalGames = (int) ($cachedScore['games_a'] ?? ($cachedScore['score_a'] ?? 0)) + (int) ($cachedScore['games_b'] ?? ($cachedScore['score_b'] ?? 0));
            $dbTotalGames = (int) ($dbScore['games_a'] ?? ($dbScore['score_a'] ?? 0)) + (int) ($dbScore['games_b'] ?? ($dbScore['score_b'] ?? 0));

            // Authoritative Completed DB Score Protection:
            // Jika DB sudah resmi Completed/Final, skor DB tidak boleh kalah oleh stale cache dengan skor lebih rendah.
            $shouldUseDb = $dbScore && (
                ! $cachedScore
                || ($dbCompleted && (! $cachedCompleted || $dbTotalGames >= $cachedTotalGames))
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
            'roundAccess'
        ));
    }

    /**
     * JSON endpoint: ambil skor terkini dari Cache / DB.
     * Dipakai untuk polling realtime via fetch().
     */
    public function getScore($gameId, $round = 'round_1')
    {
        SessionModel::findOrFail((int) $gameId);
        $cacheKey = "scoring.game_{$gameId}";
        $scores = Cache::get($cacheKey, []);

        $matchKey = request('match_key');
        $courtIndex = 0;
        if ($matchKey && preg_match('/court_(\d+)/', $matchKey, $mC)) {
            $courtIndex = max(0, ((int) $mC[1]) - 1);
        } elseif ($matchKey) {
            // matchKey explicitly provided (misal 'round_1' pada single-court)
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

        // Urutan sumber data:
        // 1. Cache match-specific
        $score = $scores[$matchKey] ?? null;

        // Fallback konsistensi untuk single-court jika key ada di format round_1 atau round_1_court_1
        if (! $score && ! $isMultiCourt && isset($scores["{$matchKey}_court_1"])) {
            $score = $scores["{$matchKey}_court_1"];
        }

        // Jika pada single-court ada data di kedua format, selalu pilih yang versinya lebih baru (newer version wins)
        if (! $isMultiCourt && isset($scores["{$matchKey}_court_1"]) && isset($scores[$matchKey])) {
            if ((int) ($scores["{$matchKey}_court_1"]['version'] ?? 0) > (int) ($scores[$matchKey]['version'] ?? 0)) {
                $score = $scores["{$matchKey}_court_1"];
            }
        }

        // 2. Authoritative Database Reconciliation:
        // Jika database memiliki skor resmi yang sudah Completed/Final, pastikan skor tidak rollback dari snapshot cache lama.
        $officialDbScore = $this->getOfficialCompletedScoreFromDatabase($gameId, $round, $courtIndex);
        $dbScore = $officialDbScore ?: $this->getScoreFromDatabase($gameId, $round, $courtIndex);

        if ($dbScore) {
            $cachedTotalGames = (int) ($score['games_a'] ?? ($score['score_a'] ?? 0)) + (int) ($score['games_b'] ?? ($score['score_b'] ?? 0));
            $dbTotalGames = (int) ($dbScore['games_a'] ?? ($dbScore['score_a'] ?? 0)) + (int) ($dbScore['games_b'] ?? ($dbScore['score_b'] ?? 0));
            $dbCompleted = ($dbScore['status'] ?? '') === 'completed';
            $cachedCompleted = ($score['status'] ?? '') === 'completed';

            if (! $score || ($dbCompleted && (! $cachedCompleted || $dbTotalGames >= $cachedTotalGames))) {
                $score = $dbScore;
                $scores[$matchKey] = $score;
                if (! $isMultiCourt) {
                    $scores[$round] = $score;
                    $scores["{$round}_court_1"] = $score;
                }
                Cache::put($cacheKey, $scores, now()->addHours(4));
            }
        }

        // 3. Fallback hanya untuk single-court jika round key ada
        if (! $score && ! $isMultiCourt && isset($scores[$round])) {
            $score = $scores[$round];
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

        $sessionActiveRound = $scores['_meta']['active_round'] ?? $this->resolveActiveRoundFromDatabase((int) $gameId) ?? $round;
        $sessionStatus = $scores['_meta']['status'] ?? 'in_progress';
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

        // score_a / score_b are only required for snapshot-based saves (fallback).
        // When action=add_point with point_won_by provided, server computes the score.
        $actionField = $request->input('action', 'add_point');
        $hasEventAction = ($actionField === 'add_point' && $request->has('point_won_by')) ||
            ($actionField === 'completion');

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

        // Guard 1: Jika ronde atau sesi sudah berstatus selesai, tolak pembaruan yang terlambat datang (late in-flight AJAX)
        if (($scores['_meta']['status'] ?? '') === 'finished' || (($scores[$matchKey]['status'] ?? '') === 'completed')) {
            return response()->json([
                'success' => true,
                'duplicate' => true,
                'already_completed' => true,
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

        if ($status === 'completed') {
            $scoringSystemName = SessionModel::where('session_id', $gameId)->value('scoring_system')
                ?? 'Total of 3';
            $scoringSystem = ScoringService::detectScoringSystem($scoringSystemName);
            $gamesA = $request->integer('games_a', 0);
            $gamesB = $request->integer('games_b', 0);

            if (! $isWalkover && ($gamesA < $scoringSystem['target_games'] && $gamesB < $scoringSystem['target_games'])) {
                return response()->json([
                    'success' => false,
                    'message' => "Target {$scoringSystem['target_games']} belum dicapai.",
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

        // Serialize cache read/compare/write so concurrent requests cannot overwrite a newer snapshot.
        $scoreLock = Cache::lock("scoring.update.{$gameId}.{$matchKey}", 10);
        $scoreLock->block(5);

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
            // Cek apakah event_id yang sama sudah pernah diproses sebelumnya.
            $processedEvents = $scores[$matchKey]['processed_events'] ?? [];
            if (! empty($eventId) && isset($processedEvents[$eventId])) {
                return response()->json([
                    'success' => true,
                    'duplicate' => true,
                    'message' => 'Event duplicate ignored.',
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
            if (($officialCompletedState['status'] ?? '') !== 'completed') {
                $dbScore = $this->getScoreFromDatabase($gameId, $round, $courtIndex);
                if ($dbScore && ($dbScore['status'] ?? '') === 'completed') {
                    $officialCompletedState = $dbScore;
                }
            }

            if (($officialCompletedState['status'] ?? '') !== 'completed') {
                $rawFinalScore = $this->getOfficialCompletedScoreFromDatabase($gameId, $round, $courtIndex);
                if ($rawFinalScore) {
                    $officialCompletedState = $rawFinalScore;
                }
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
                ])->header('Cache-Control', 'no-cache, no-store, must-revalidate');
            }

            $isMerged = false;

            if ($action === 'batch_events') {
                $batchEvents = $request->input('events', []);
                $scorePayload = $currentState;
                foreach ($batchEvents as $ev) {
                    $evAction = $ev['action'] ?? 'add_point';
                    $evEventId = $ev['event_id'] ?? '';
                    $evTeam = $ev['team'] ?? '';

                    if (! empty($evEventId) && isset($processedEvents[$evEventId])) {
                        continue;
                    }

                    if ($evAction === 'add_point' && ! empty($evTeam) && in_array($evTeam, ['A', 'B']) && ($scorePayload['status'] ?? '') !== 'completed') {
                        $scorePayload = $this->applyPointDeltaToState($scorePayload, $evTeam, $scoringSystem);
                    } elseif ($evAction === 'completion' || ($ev['status'] ?? '') === 'completed') {
                        $evGamesA = isset($ev['games_a']) ? (int) $ev['games_a'] : (isset($ev['snapshot']['gamesA']) ? (int) $ev['snapshot']['gamesA'] : null);
                        $evGamesB = isset($ev['games_b']) ? (int) $ev['games_b'] : (isset($ev['snapshot']['gamesB']) ? (int) $ev['snapshot']['gamesB'] : null);
                        $scorePayload = $this->applyCompletionToState($scorePayload, $scoringSystem, $ev['team'] ?? null, $evGamesA, $evGamesB);
                    }

                    if (! empty($evEventId)) {
                        $processedEvents[$evEventId] = true; // Temporary mark to prevent intra-batch dupes
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
                        $scorePayload['games_a'] = $finalGamesA;
                        $scorePayload['games_b'] = $finalGamesB;
                        $scorePayload['score_a'] = $finalGamesA;
                        $scorePayload['score_b'] = $finalGamesB;
                        $scorePayload['status'] = 'completed';

                        $scorePayload['sets_a'] =
                            $finalGamesA > $finalGamesB ? 1 : 0;

                        $scorePayload['sets_b'] =
                            $finalGamesB > $finalGamesA ? 1 : 0;
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
            $scores[$matchKey] = $scorePayload;
            if (! $isMultiCourt) {
                $scores[$round] = $scorePayload;
                $scores["{$round}_court_1"] = $scorePayload;
            }

            Cache::put($cacheKey, $scores, now()->addHours(4));

            // Persistensi ke Database (tb_match, tb_match_participant, tb_score) secara ATOMIC di dalam DB::transaction
            try {
                DB::transaction(function () use ($gameId, $round, $matchKey, $scorePayload, $newVersion, $eventId, $request) {
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

                            // Simpan / update ke tb_score
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
                        }
                    }
                });
            } catch (\Throwable $e) {
                Log::warning('Failed to persist live score to database: '.$e->getMessage());
            }

            // Broadcast state terbaru ke Supabase Realtime (Requirement 4 & 5)
            $this->broadcastScoreUpdateRealtime($gameId, $matchKey, $scorePayload);
        } finally {
            $scoreLock->release();
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
        ])->header('Cache-Control', 'no-cache, no-store, must-revalidate');
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

        // 1. Validasi: Ronde aktif saat ini harus benar-benar selesai di SELURUH court
        if (! $this->isRoundFullyCompleted($game, $currentRound, $savedScores)) {
            return redirect()->route('scoring.live', [
                'id' => $gameId,
                'format' => request('format', $game['match_format'] ?? 'Americano'),
                'round' => $currentRound,
                'court' => (int) $request->input('court', 0),
            ])->with('warning', 'Set/Ronde saat ini belum selesai. Selesaikan seluruh pertandingan terlebih dahulu.');
        }

        // 2. Validasi: Ronde tujuan harus unlocked secara berurutan
        $roundAccess = $this->getRoundAccess($game, $scoringSystem, $savedScores);
        if (! ($roundAccess[$nextRound] ?? false)) {
            return redirect()->route('scoring.live', [
                'id' => $gameId,
                'format' => request('format', $game['match_format'] ?? 'Americano'),
                'round' => $currentRound,
                'court' => (int) $request->input('court', 0),
            ])->with('warning', 'Set/Ronde saat ini belum selesai. Selesaikan seluruh pertandingan terlebih dahulu.');
        }

        // 3. Update active_round pada metadata Cache
        $savedScores['_meta'] = array_merge($savedScores['_meta'] ?? [], [
            'active_round' => $nextRound,
            'updated_at' => now()->toDateTimeString(),
        ]);
        Cache::put($cacheKey, $savedScores, now()->addHours(4));

        // 4. Broadcast perpindahan ronde ke seluruh penonton/player via Supabase Realtime
        $this->broadcastRoundAdvancedRealtime($gameId, $currentRound, $nextRound);

        $isTeamFormat = str_contains(strtolower($game['match_format'] ?? ''), 'team');
        $unitLabel = $isTeamFormat ? 'Set' : 'Ronde';

        return redirect()->route('scoring.live', [
            'id' => $gameId,
            'format' => request('format', $game['match_format'] ?? 'Americano'),
            'round' => $nextRound,
            'court' => (int) $request->input('court', 0),
        ])->with('success', "Berhasil lanjut ke {$unitLabel} {$nextNum}!");
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

        if (! $isWalkover && ($gamesA < $system['target_games'] && $gamesB < $system['target_games'])) {
            return redirect()->back()->withErrors([
                'score' => "Target {$system['target_games']} belum dicapai.",
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
                    $matchScores = $match->scores;
                    $mSetsA = 0;
                    $mSetsB = 0;
                    $mGamesA = 0;
                    $mGamesB = 0;
                    $mSetHistory = [];

                    foreach ($matchScores as $sc) {
                        $mSetsA = max($mSetsA, (int) $sc->set_score_a);
                        $mSetsB = max($mSetsB, (int) $sc->set_score_b);
                        $mGamesA += (int) $sc->game_score_a;
                        $mGamesB += (int) $sc->game_score_b;
                        if ($scoringSystem['is_sets']) {
                            $mSetHistory[] = [
                                'set' => (int) $sc->set_number,
                                'score_a' => (int) $sc->score_side_a,
                                'score_b' => (int) $sc->score_side_b,
                            ];
                        }
                    }

                    if ($matchScores->isNotEmpty() || $match->status_match === 'Completed') {
                        $dbScoresRecovered = true;
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
                            'set_history' => $mSetHistory,
                            'scoring_type' => $scoringSystem['type'],
                            'scoring_system' => $scoringSystem['type'],
                            'winner_team' => $match->winner_team ?: ($mGamesA >= $mGamesB ? 'Team A' : 'Team B'),
                            'status' => 'completed',
                            'updated_at' => $match->updated_at ? $match->updated_at->toDateTimeString() : now()->toDateTimeString(),
                        ];
                        $savedScores['_meta'] = [
                            'finished_at' => $match->updated_at ? $match->updated_at->toDateTimeString() : now()->toDateTimeString(),
                            'last_round_key' => $rKey,
                            'status' => 'finished',
                        ];
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
            $completedMatchesCount = $dbMatches->where('status_match', 'Completed')->count();
        }

        // Cek apakah ada skor yang selesai dicatat
        $hasScores = $completedMatchesCount > 0;
        if (! $hasScores) {
            foreach ($savedScores as $k => $v) {
                if ($k !== '_meta' && is_array($v) && ($v['status'] ?? '') === 'completed') {
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
            'userGivenKudos'
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

    private function buildPlayerRecap(array $game, array $rankedPlayers, ?string $requestedPlayerName = null): array
    {
        $selectedPlayer = null;
        $user = Auth::user();

        if ($requestedPlayerName) {
            $selectedPlayer = Player::where('nama', $requestedPlayerName)->first();
        } elseif ($user) {
            $selectedPlayer = Player::where('user_id', $user->user_id)->first();
        }

        $playerName = $requestedPlayerName ?: $selectedPlayer?->nama;
        $playerStats = collect($rankedPlayers)->first(function (array $player) use ($playerName) {
            return $playerName && ScoringService::cleanPlayerName($player['name']) === ScoringService::cleanPlayerName($playerName);
        });

        $playerStats ??= $rankedPlayers[0] ?? [
            'name' => $playerName ?: 'Pemain Matcha',
            'points_for' => 0,
            'matches' => 0,
            'wins' => 0,
        ];
        $playerName = $playerStats['name'];

        $participant = collect($game['participants'] ?? [])->first(function (array $participant) use ($playerName) {
            return ScoringService::cleanPlayerName($participant['name'] ?? '') === ScoringService::cleanPlayerName($playerName);
        });
        $durationMinutes = 0;

        try {
            $drawing = Drawing::where('session_id', $game['id'])->first();
            if ($drawing) {
                $matches = GameMatch::with('participants.player')
                    ->where('drawing_id', $drawing->drawing_id)
                    ->where('status_match', 'Completed')
                    ->get();

                foreach ($matches as $match) {
                    $isParticipant = $selectedPlayer
                        ? $match->participants->contains('player_id', $selectedPlayer->player_id)
                        : $match->participants->contains(function ($matchParticipant) use ($playerName) {
                            return ScoringService::cleanPlayerName($matchParticipant->player?->nama ?? '')
                                === ScoringService::cleanPlayerName($playerName);
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
            'player_name' => $playerName,
            'avatar' => $participant['avatar'] ?? null,
            'level' => $participant['level'] ?? ($playerStats['level'] ?? 'Intermediate'),
            'total_points' => (int) ($playerStats['points_for'] ?? 0),
            'duration_played' => $durationMinutes > 0 ? round($durationMinutes).'m' : '0m',
            'wins' => $wins,
            'losses' => max(0, $matchesPlayed - $wins),
            'win_rate' => $matchesPlayed > 0 ? round(($wins / $matchesPlayed) * 100).'%' : '0%',
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

                if (! $isLocked && $currentParticipantNames !== $cachedPlayerNames) {
                    $isCacheStale = true;
                }
            }

            if ($cachedSchedule && ! empty($cachedSchedule['rounds']) && ! $isCacheStale) {
                $rounds = $cachedSchedule['rounds'];
                $courtCount = $cachedSchedule['court_count'] ?? $courtCount;
            } else {
                try {
                    if (str_contains($format, 'team') && count($participants) >= 4 && count($participants) % 2 === 0) {
                        $teamService = new TeamAmericanoService;
                        $scoringSystem = ScoringService::detectScoringSystem($dbSession->scoring_system ?? 'Total of 3');
                        $roundCount = $scoringSystem['is_sets'] ? $scoringSystem['max_sets'] : 1;
                        $drawingData = $teamService->generateTeamRounds($participants, $courtCount, null, $roundCount);
                        $rounds = $drawingData['rounds'] ?? [];
                    } else {
                        // Gunakan jenis_permainan dari session agar Single/Double benar
                        $americanoService = new AmericanoService;
                        $scoringSystem = ScoringService::detectScoringSystem($dbSession->scoring_system ?? 'Total of 3');
                        $roundCount = $scoringSystem['is_sets'] ? $scoringSystem['max_sets'] : 1;
                        $rounds = $americanoService->generateRounds($participants, $courtCount, $roundCount, $sessionJenisPermainan ?? 'Double');
                    }
                } catch (\Throwable $e) {
                    $americanoService = new AmericanoService;
                    $scoringSystem = ScoringService::detectScoringSystem($dbSession->scoring_system ?? 'Total of 3');
                    $roundCount = $scoringSystem['is_sets'] ? $scoringSystem['max_sets'] : 1;
                    $rounds = $americanoService->generateRounds($participants, $courtCount, $roundCount, $sessionJenisPermainan ?? 'Double');
                }
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
                'court_name' => $dbSession->courts->first()->nama_court ?? 'Court 1',
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
     * Cek apakah seluruh match dan court pada round tertentu sudah selesai (status === 'completed').
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
            if (! $score || ($score['status'] ?? '') !== 'completed') {
                $dbScore = $this->getScoreFromDatabase($game['id'], $roundKey, 0);
                if ($dbScore && ($dbScore['status'] ?? '') === 'completed') {
                    $score = $dbScore;
                }
            }

            return ($score['status'] ?? '') === 'completed';
        }

        foreach ($matches as $matchIndex => $match) {
            $matchKey = $matchCount > 1
                ? "{$roundKey}_court_".($matchIndex + 1)
                : $roundKey;
            $score = $savedScores[$matchKey] ?? null;

            if (! $score && $matchCount === 1 && isset($savedScores["{$roundKey}_court_1"])) {
                $score = $savedScores["{$roundKey}_court_1"];
            }

            // Cross-check ke database jika score belum ada di cache atau statusnya belum completed
            if (! $score || ($score['status'] ?? '') !== 'completed') {
                $dbScore = $this->getScoreFromDatabase($game['id'], $roundKey, $matchIndex);
                if ($dbScore && ($dbScore['status'] ?? '') === 'completed') {
                    $score = $dbScore;
                }
            }

            if (($score['status'] ?? '') !== 'completed') {
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
        if ($scoringSystem['is_sets']) {
            $numSets = max(count($drawingRounds), (int) ($scoringSystem['max_sets'] ?? 1));
            $roundKeys = array_map(fn ($roundNumber) => "round_{$roundNumber}", range(1, $numSets));
        } else {
            $roundKeys = ! empty($drawingRounds) ? $drawingRounds : ['round_1'];
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
     * Ambil skor dari database (tb_match & tb_score) untuk round & court tertentu.
     */
    protected function getScoreFromDatabase($gameId, $round, $courtIndex = 0)
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

            $scores = Score::where('match_id', $match->match_id)->orderBy('set_number', 'asc')->orderBy('version', 'desc')->orderBy('score_id', 'desc')->get();
            if ($scores->isEmpty() && strtolower($match->status_match) !== 'completed') {
                return null;
            }

            $isCompleted = strtolower((string) ($match->status_match ?? '')) === 'completed';
            if ($isCompleted) {
                $officialScore = $scores
                    ->filter(fn ($sc) => in_array(strtolower((string) ($sc->status_score ?? '')), ['final', 'completed'], true))
                    ->sortByDesc('version')
                    ->sortByDesc('score_id')
                    ->first();
                $lastScore = $officialScore ?: $scores->sortByDesc('version')->sortByDesc('score_id')->first();
            } else {
                $lastScore = $scores->sortByDesc('version')->sortByDesc('score_id')->first();
            }
            $setHistory = [];
            $setsA = 0;
            $setsB = 0;
            $gamesA = 0;
            $gamesB = 0;

            foreach ($scores as $sc) {
                $setsA = max($setsA, (int) $sc->set_score_a);
                $setsB = max($setsB, (int) $sc->set_score_b);
                $setHistory[] = [
                    'set' => (int) $sc->set_number,
                    'score_a' => (int) ($sc->game_score_a ?? $sc->score_side_a ?? 0),
                    'score_b' => (int) ($sc->game_score_b ?? $sc->score_side_b ?? 0),
                ];
            }

            if ($lastScore) {
                $gamesA = (int) ($lastScore->game_score_a ?? $lastScore->score_side_a ?? 0);
                $gamesB = (int) ($lastScore->game_score_b ?? $lastScore->score_side_b ?? 0);
                $setsA = max($setsA, (int) $lastScore->set_score_a);
                $setsB = max($setsB, (int) $lastScore->set_score_b);
            }

            $status = $isCompleted ? 'completed' : 'in_progress';

            return [
                'match_id' => $match->match_id,
                'version' => (int) ($lastScore->version ?? ($match->version ?? 1)),
                'updated_at_ms' => $match->updated_at ? (int) round($match->updated_at->timestamp * 1000) : (int) round(microtime(true) * 1000),
                'score_a' => $gamesA,
                'score_b' => $gamesB,
                'point_display_a' => (string) ($lastScore->point_score_a ?? '0'),
                'point_display_b' => (string) ($lastScore->point_score_b ?? '0'),
                'set_number' => (int) ($lastScore->set_number ?? 1),
                'sets_a' => $setsA,
                'sets_b' => $setsB,
                'games_a' => $gamesA,
                'games_b' => $gamesB,
                'set_history' => $setHistory,
                'idx_a' => 0,
                'idx_b' => 0,
                'is_deuce' => false,
                'advantage' => null,
                'scoring_type' => $lastScore->scoring_system ?? 'total_of_sets',
                'status' => $status,
                'winner_team' => $match->winner_team,
            ];
        } catch (\Throwable $e) {
            Log::warning('Failed to get score from database: '.$e->getMessage());

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

        if (! $row && strtolower((string) ($match->status_match ?? '')) !== 'completed') {
            return null;
        }

        $row = $row ?: DB::table('tb_score')->where('match_id', $match->match_id)->orderByDesc('version')->orderByDesc('score_id')->first();
        if (! $row) {
            return null;
        }

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
            'status' => strtolower((string) ($match->status_match ?? '')) === 'completed' || in_array(strtolower((string) ($row->status_score ?? '')), ['final', 'completed'], true) ? 'completed' : 'in_progress',
            'winner_team' => $match->winner_team,
        ];
    }

    /**
     * Terapkan mutasi 1 poin ('A' atau 'B') ke state match terkini (3-Way Delta Merge).
     */
    protected function applyPointDeltaToState(array $currentState, string $team, array $scoringSystem): array
    {
        $tennisPoints = ['0', '15', '30', '40'];
        $idxA = (int) ($currentState['idx_a'] ?? 0);
        $idxB = (int) ($currentState['idx_b'] ?? 0);
        $isDeuce = (bool) ($currentState['is_deuce'] ?? false);
        $advantage = $currentState['advantage'] ?? null;
        $gamesA = (int) ($currentState['games_a'] ?? ($currentState['score_a'] ?? 0));
        $gamesB = (int) ($currentState['games_b'] ?? ($currentState['score_b'] ?? 0));
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
        if (! $isSets) {
            if ($targetGames > 0 && $gamesA >= $targetGames) {
                $status = 'completed';
                $winnerTeam = 'Team A';
                $setsA = 1;
            } elseif ($targetGames > 0 && $gamesB >= $targetGames) {
                $status = 'completed';
                $winnerTeam = 'Team B';
                $setsB = 1;
            }
        } else {
            if (($gamesA >= 6 && $gamesA - $gamesB >= 2) || ($gamesA === 7 && $gamesB === 6)) {
                $status = 'completed';
                $winnerTeam = 'Team A';
                $setsA = 1;
            } elseif (($gamesB >= 6 && $gamesB - $gamesA >= 2) || ($gamesB === 7 && $gamesA === 6)) {
                $status = 'completed';
                $winnerTeam = 'Team B';
                $setsB = 1;
            }
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

        $winner = $explicitWinner ?: ($gamesA >= $gamesB ? 'Team A' : 'Team B');
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
            $key = config('services.supabase.key');
            if (empty($url) || empty($key)) {
                return;
            }

            $cacheKey = "scoring.game_{$gameId}";
            $savedScores = Cache::get($cacheKey, []);
            $sessionActiveRound = $savedScores['_meta']['active_round'] ?? 'round_1';
            $sessionStatus = $savedScores['_meta']['status'] ?? 'in_progress';

            Http::withoutVerifying()
                ->withHeaders([
                    'apikey' => $key,
                    'Authorization' => 'Bearer '.$key,
                    'Content-Type' => 'application/json',
                ])
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
        } catch (\Throwable $e) {
            Log::debug("Supabase realtime broadcast skipped: {$e->getMessage()}");
        }
    }

    /**
     * Broadcast perpindahan ronde / set ke Supabase Realtime Channel.
     */
    protected function broadcastRoundAdvancedRealtime(int $gameId, string $currentRound, string $nextRound): void
    {
        try {
            $url = rtrim(config('services.supabase.url', ''), '/').'/realtime/v1/api/broadcast';
            $key = config('services.supabase.key');
            if (empty($url) || empty($key)) {
                return;
            }

            Http::withoutVerifying()
                ->withHeaders([
                    'apikey' => $key,
                    'Authorization' => 'Bearer '.$key,
                    'Content-Type' => 'application/json',
                ])
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
                                'timestamp' => now()->toIso8601String(),
                            ],
                        ],
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::debug("Supabase realtime round advanced broadcast skipped: {$e->getMessage()}");
        }
    }

    /**
     * Broadcast penyelesaian seluruh sesi pertandingan ke Supabase Realtime Channel.
     */
    protected function broadcastSessionFinishedRealtime(int $gameId, string $recapUrl): void
    {
        try {
            $url = rtrim(config('services.supabase.url', ''), '/').'/realtime/v1/api/broadcast';
            $key = config('services.supabase.key');
            if (empty($url) || empty($key)) {
                return;
            }

            Http::withoutVerifying()
                ->withHeaders([
                    'apikey' => $key,
                    'Authorization' => 'Bearer '.$key,
                    'Content-Type' => 'application/json',
                ])
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
        } catch (\Throwable $e) {
            Log::debug("Supabase realtime session finished broadcast skipped: {$e->getMessage()}");
        }
    }

    /**
     * Cari ronde yang sedang aktif dari database (tb_match) jika cache metadata belum ada.
     */
    protected function resolveActiveRoundFromDatabase(int $gameId): string
    {
        try {
            $drawing = Drawing::where('session_id', $gameId)->first();
            if (! $drawing) {
                return 'round_1';
            }

            $session = SessionModel::with('courts')->find($gameId);
            $courtCount = $session ? max(1, $session->courts->count()) : 1;

            $matches = GameMatch::where('drawing_id', $drawing->drawing_id)
                ->orderBy('nomor_match', 'asc')
                ->get();

            if ($matches->isEmpty()) {
                return 'round_1';
            }

            // Cari match pertama yang berstatus 'In Progress'
            $inProgressMatch = $matches->first(fn ($m) => strtolower((string) ($m->status_match ?? '')) === 'in progress');
            if ($inProgressMatch) {
                $rNum = intdiv(max(0, ((int) $inProgressMatch->nomor_match) - 1), $courtCount) + 1;

                return "round_{$rNum}";
            }

            // Jika semua match yang tercatat sudah selesai, ambil ronde match terakhir
            $lastCompletedMatch = $matches->filter(fn ($m) => strtolower((string) ($m->status_match ?? '')) === 'completed')->last();
            if ($lastCompletedMatch) {
                $rNum = intdiv(max(0, ((int) $lastCompletedMatch->nomor_match) - 1), $courtCount) + 1;

                return "round_{$rNum}";
            }

            return 'round_1';
        } catch (\Throwable $e) {
            return 'round_1';
        }
    }
}
