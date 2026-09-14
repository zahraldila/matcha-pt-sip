<?php

namespace App\Http\Controllers\Scoring;

use App\Http\Controllers\Controller;
use App\Models\SessionModel;
use App\Models\Drawing;
use App\Models\GameMatch;
use App\Models\Score;
use App\Models\PlayingHistory;
use App\Models\Player;
use App\Services\MatchaDummyDataService;
use App\Services\Scoring\ScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ScoringController extends Controller
{
    /**
     * Live Match Scoring Console.
     * Menampilkan scoring board aktif berdasarkan data game & round yang dipilih.
     */
    public function live($id = 1)
    {
        $game = $this->getGameData($id);

        // Kunci drawing saat live scoring dibuka (match mulai berjalan)
        Cache::put("drawing.locked_{$id}", true, now()->addHours(6));

        // Tentukan round aktif (bisa dari query param atau default round_1)
        $activeRound = request('round', 'round_1');
        $courtIndex  = (int) request('court', 0);

        // Bangun konteks match (tim A, tim B, istirahat, court_name, matches) untuk round aktif & court
        $matchContext = ScoringService::buildMatchContext($game, $activeRound, $courtIndex);

        // Deteksi scoring system
        $scoringSystem = ScoringService::detectScoringSystem($game['scoring_system'] ?? '');

        // Ambil skor tersimpan dari Cache (shared antar semua user)
        $cacheKey    = "scoring.game_{$game['id']}";
        $savedScores = Cache::get($cacheKey, []);
        $courtCount  = $matchContext['court_count'] ?? 1;
        $matchKey    = ($courtCount > 1) ? "{$activeRound}_court_" . ($courtIndex + 1) : $activeRound;

        // Restore & Ensure matches for all courts in this round
        $cacheUpdated = false;
        foreach ($matchContext['matches'] as $mIdx => $m) {
            $mKey = ($courtCount > 1) ? "{$activeRound}_court_" . ($mIdx + 1) : $activeRound;
            // Ensure match & participants exist in DB
            ScoringService::ensureMatchAndParticipants($game['id'], $activeRound, $mIdx, $matchContext);

            // If Cache does not have score for this match key, restore from DB
            if (!isset($savedScores[$mKey])) {
                $dbScore = $this->getScoreFromDatabase($game['id'], $activeRound, $mIdx);
                if ($dbScore) {
                    $savedScores[$mKey] = $dbScore;
                    $cacheUpdated = true;
                }
            }
        }
        if ($cacheUpdated) {
            Cache::put($cacheKey, $savedScores, now()->addHours(4));
        }

        $defaultScore = [
            'score_a'         => 0,
            'score_b'         => 0,
            'point_display_a' => '0',
            'point_display_b' => '0',
            'set_number'      => 1,
            'sets_a'          => 0,
            'sets_b'          => 0,
            'games_a'         => 0,
            'games_b'         => 0,
            'set_history'     => [],
            'idx_a'           => 0,
            'idx_b'           => 0,
            'is_deuce'        => false,
            'advantage'       => null,
            'status'          => 'in_progress',
            'winner_team'     => null,
        ];
        // Multi-court NEVER falls back to $savedScores[$activeRound]
        $matchScore = $savedScores[$matchKey] ?? ($courtCount > 1 ? [] : ($savedScores[$activeRound] ?? []));
        $currentScore = array_merge($defaultScore, $matchScore);

        // Deteksi role & player status
        $user = Auth::user();
        $userRole = $user ? $user->role : 'guest';
        $isHost   = ($userRole === 'host');
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
            'cacheKey'
        ));
    }

    /**
     * JSON endpoint: ambil skor terkini dari Cache / DB.
     * Dipakai untuk polling realtime via fetch().
     */
    public function getScore($gameId, $round = 'round_1')
    {
        $cacheKey = "scoring.game_{$gameId}";
        $scores   = Cache::get($cacheKey, []);

        $matchKey = request('match_key');
        $courtIndex = 0;
        if ($matchKey && preg_match('/court_(\d+)/', $matchKey, $mC)) {
            $courtIndex = max(0, ((int) $mC[1]) - 1);
        } elseif (request()->has('court_num')) {
            $courtNum = (int) request('court_num');
            $courtIndex = max(0, $courtNum - 1);
            $matchKey = "{$round}_court_{$courtNum}";
        } elseif (request()->has('court')) {
            $courtIndex = (int) request('court');
            $courtNum = $courtIndex + 1;
            $matchKey = "{$round}_court_{$courtNum}";
        }

        if (!$matchKey) {
            $matchKey = $round;
        }

        $isMultiCourt = str_contains($matchKey, '_court_');

        // Urutan sumber data:
        // 1. Cache match-specific
        $score = $scores[$matchKey] ?? null;

        // 2. Jika tidak ada di Cache: ambil dari tb_score via match_id di database
        if (!$score) {
            $dbScore = $this->getScoreFromDatabase($gameId, $round, $courtIndex);
            if ($dbScore) {
                $score = $dbScore;
                $scores[$matchKey] = $dbScore;
                Cache::put($cacheKey, $scores, now()->addHours(4));
            }
        }

        // 3. Fallback hanya untuk single-court jika round key ada
        if (!$score && !$isMultiCourt && isset($scores[$round])) {
            $score = $scores[$round];
        }

        // 4. Default state jika masih kosong (DILARANG fallback ke court atau round lain untuk multi-court)
        if (!$score) {
            $score = [];
        }

        return response()->json([
            'game_id'         => (int) $gameId,
            'round'           => $round,
            'match_key'       => $matchKey,
            'version'         => (int) ($score['version'] ?? 0),
            'updated_at_ms'   => (int) ($score['updated_at_ms'] ?? 0),
            'score_a'         => (int) ($score['score_a'] ?? 0),
            'score_b'         => (int) ($score['score_b'] ?? 0),
            'point_display_a' => (string) ($score['point_display_a'] ?? ($score['score_a'] ?? '0')),
            'point_display_b' => (string) ($score['point_display_b'] ?? ($score['score_b'] ?? '0')),
            'set_number'      => (int) ($score['set_number'] ?? 1),
            'sets_a'          => (int) ($score['sets_a'] ?? 0),
            'sets_b'          => (int) ($score['sets_b'] ?? 0),
            'games_a'         => (int) ($score['games_a'] ?? 0),
            'games_b'         => (int) ($score['games_b'] ?? 0),
            'set_history'     => $score['set_history'] ?? [],
            'idx_a'           => (int) ($score['idx_a'] ?? 0),
            'idx_b'           => (int) ($score['idx_b'] ?? 0),
            'is_deuce'        => (bool) ($score['is_deuce'] ?? false),
            'advantage'       => $score['advantage'] ?? null,
            'scoring_type'    => $score['scoring_type'] ?? 'total_of_sets',
            'status'          => $score['status'] ?? 'in_progress',
            'winner_team'     => $score['winner_team'] ?? null,
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
        // Auth check: Host atau participant/player dalam sesi ini
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Silakan login terlebih dahulu.',
            ], 401);
        }

        $user   = Auth::user();
        $isHost = ($user->role === 'host');
        $gameId = $request->integer('game_id');

        // Jika bukan host, cek apakah user merupakan participant/player pada session ini
        if (!$isHost) {
            $isParticipant = false;
            $player = Player::where('user_id', $user->user_id)->first();
            if ($player) {
                $isParticipant = \DB::table('tb_session_player')
                    ->where('session_id', $gameId)
                    ->where('player_id', $player->player_id)
                    ->exists();
            }
            if (!$isParticipant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Hanya Host atau pemain dalam sesi ini yang dapat mencatat skor.',
                ], 403);
            }
        }

        $request->validate([
            'game_id'         => 'required|integer',
            'round'           => 'required|string',
            'match_key'       => 'nullable|string',
            'court'           => 'nullable|integer',
            'score_a'         => 'required|integer|min:0',
            'score_b'         => 'required|integer|min:0',
            'point_display_a' => 'nullable|string',
            'point_display_b' => 'nullable|string',
            'set_number'      => 'nullable|integer|min:1',
            'sets_a'          => 'nullable|integer|min:0',
            'sets_b'          => 'nullable|integer|min:0',
            'games_a'         => 'nullable|integer|min:0',
            'games_b'         => 'nullable|integer|min:0',
            'set_history'     => 'nullable|array',
            'idx_a'           => 'nullable|integer|min:0',
            'idx_b'           => 'nullable|integer|min:0',
            'is_deuce'        => 'nullable|boolean',
            'advantage'       => 'nullable|string',
            'scoring_type'    => 'nullable|string',
            'status'          => 'nullable|string|in:in_progress,completed',
            'winner_team'     => 'nullable|string',
        ]);

        $round    = $request->string('round')->toString();
        $matchKey = $request->input('match_key');
        if (!$matchKey) {
            $courtNum = $request->integer('court', 0);
            $matchKey = ($courtNum > 0) ? "{$round}_court_{$courtNum}" : $round;
        }
        $status = $request->input('status', 'in_progress');

        // Simpan ke Cache (TTL 4 jam) agar bisa dibaca semua user
        $cacheKey = "scoring.game_{$gameId}";
        $scores   = Cache::get($cacheKey, []);

        // Guard 1: Jika ronde atau sesi sudah berstatus selesai, tolak pembaruan yang terlambat datang (late in-flight AJAX)
        if (($scores['_meta']['status'] ?? '') === 'finished' || (($scores[$matchKey]['status'] ?? '') === 'completed')) {
            return response()->json([
                'success'   => false,
                'message'   => 'Pertandingan sudah selesai. Pembaruan skor diabaikan.',
                'saved'     => $scores[$matchKey] ?? [],
                'match_key' => $matchKey,
            ]);
        }

        // Guard 2: Cek apakah sesi di database sudah berstatus Finished
        try {
            $dbSessionStatus = SessionModel::where('session_id', $gameId)->value('status_session');
            if (strtolower($dbSessionStatus ?? '') === 'finished') {
                return response()->json([
                    'success'   => false,
                    'message'   => 'Sesi pertandingan sudah selesai di database. Pembaruan skor diabaikan.',
                    'saved'     => $scores[$matchKey] ?? [],
                    'match_key' => $matchKey,
                ]);
            }
        } catch (\Throwable $e) {
            // Abaikan kegagalan koneksi DB sekunder pada update AJAX realtime
        }

        // Monotonic version per matchKey
        $prevVersion = (int) ($scores[$matchKey]['version'] ?? 0);
        $newVersion = $prevVersion + 1;
        $serverTimeMs = (int) round(microtime(true) * 1000);

        $clientId = (string) $request->input('client_id', '');
        $clientSeq = (int) $request->input('client_seq', $request->input('client_version', 0));
        $prevClientSeq = (int) ($scores[$matchKey]['clients'][$clientId] ?? 0);

        // Jika request berasal dari client yang sama dan datang terlambat (out-of-order),
        // tolak penimpaan skor agar skor tidak mundur.
        if (!empty($clientId) && $clientSeq > 0 && $prevClientSeq > 0 && $clientSeq < $prevClientSeq) {
            return response()->json([
                'success'        => true,
                'message'        => 'Stale update ignored.',
                'saved'          => $scores[$matchKey],
                'match_key'      => $matchKey,
                'version'        => (int) ($scores[$matchKey]['version'] ?? 0),
                'server_time_ms' => $serverTimeMs,
                'client_seq'     => $clientSeq,
                'stale_ignored'  => true,
            ])->header('Cache-Control', 'no-cache, no-store, must-revalidate');
        }

        // Pertahankan map client sequences
        $clientMap = $scores[$matchKey]['clients'] ?? [];
        if (!empty($clientId)) {
            $clientMap[$clientId] = max($clientSeq, $prevClientSeq);
        }

        $scorePayload = [
            'version'         => $newVersion,
            'updated_at_ms'   => $serverTimeMs,
            'clients'         => $clientMap,
            'score_a'         => $request->integer('score_a'),
            'score_b'         => $request->integer('score_b'),
            'point_display_a' => (string) $request->input('point_display_a', '0'),
            'point_display_b' => (string) $request->input('point_display_b', '0'),
            'set_number'      => $request->integer('set_number', 1),
            'sets_a'          => $request->integer('sets_a', 0),
            'sets_b'          => $request->integer('sets_b', 0),
            'games_a'         => $request->integer('games_a', 0),
            'games_b'         => $request->integer('games_b', 0),
            'set_history'     => $request->input('set_history', []),
            'idx_a'           => $request->integer('idx_a', 0),
            'idx_b'           => $request->integer('idx_b', 0),
            'is_deuce'        => (bool) $request->input('is_deuce', false),
            'advantage'       => $request->input('advantage', null),
            'scoring_type'    => $request->input('scoring_type', 'total_of_sets'),
            'status'          => $status,
            'winner_team'     => $request->input('winner_team', null),
            'updated_at'      => now()->toDateTimeString(),
        ];

        $isMultiCourt = str_contains($matchKey, '_court_');
        $scores[$matchKey] = $scorePayload;
        // DILARANG fallback / sync ke $scores[$round] untuk multi-court agar tidak terjadi cross-court pollution
        if (!$isMultiCourt) {
            $scores[$round] = $scorePayload;
        }

        Cache::put($cacheKey, $scores, now()->addHours(4));

        // Persistensi ke Database (tb_match, tb_match_participant, tb_score)
        try {
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
                    $matchSummary = "Game Score {$scorePayload['games_a']} - {$scorePayload['games_b']}";
                    $match->status_match = ($status === 'completed') ? 'Completed' : 'In Progress';
                    $match->hasil_pertandingan = $matchSummary;
                    if ($status === 'completed' && !empty($scorePayload['winner_team'])) {
                        $match->winner_team = $scorePayload['winner_team'];
                        $match->waktu_selesai = now();
                    }
                    $match->save();

                    // Simpan / update ke tb_score
                    $setNumber = $scorePayload['set_number'] ?? 1;
                    Score::updateOrCreate(
                        [
                            'match_id'   => $match->match_id,
                            'set_number' => $setNumber,
                        ],
                        [
                            'game_number'    => 1,
                            'point_score_a'  => $scorePayload['point_display_a'] ?? '0',
                            'point_score_b'  => $scorePayload['point_display_b'] ?? '0',
                            'game_score_a'   => $scorePayload['games_a'],
                            'game_score_b'   => $scorePayload['games_b'],
                            'set_score_a'    => $scorePayload['sets_a'],
                            'set_score_b'    => $scorePayload['sets_b'],
                            'score_side_a'   => $scorePayload['games_a'],
                            'score_side_b'   => $scorePayload['games_b'],
                            'scoring_system' => $scorePayload['scoring_type'] ?? 'total_of_sets',
                            'status_score'   => ($status === 'completed') ? 'Final' : 'In Progress',
                        ]
                    );
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Failed to persist live score to database: " . $e->getMessage());
        }

        return response()->json([
            'success'        => true,
            'message'        => 'Skor berhasil disimpan.',
            'saved'          => $scores[$matchKey],
            'match_key'      => $matchKey,
            'version'        => $newVersion,
            'server_time_ms' => $serverTimeMs,
            'client_seq'     => $request->input('client_seq'),
        ]);
    }

    /**
     * Tandai sesi sebagai selesai dan redirect ke halaman recap.
     * Simpan juga data ke database: tb_drawing, tb_match, tb_score, tb_playing_history.
     */
    public function finishSession(Request $request)
    {
        // Hanya host yang boleh menyelesaikan sesi
        if (!Auth::check() || Auth::user()->role !== 'host') {
            return redirect()->back()->withErrors(['auth' => 'Akses ditolak. Hanya Host yang dapat menyelesaikan sesi.']);
        }

        $request->validate([
            'game_id'  => 'required|integer',
            'round'    => 'required|string',
        ]);

        $gameId = $request->integer('game_id');
        $round  = $request->string('round')->toString();
        $matchKey = $request->input('match_key');
        if (!$matchKey) {
            $courtNum = $request->integer('court', 0);
            $matchKey = ($courtNum > 0) ? "{$round}_court_{$courtNum}" : $round;
        }

        $game = $this->getGameData($gameId);
        $scoringSystemName = $request->input('scoring_system', $game['scoring_system'] ?? 'Total of 3');
        $system = ScoringService::detectScoringSystem($scoringSystemName);

        // Ambil data skor dari request atau fallback ke cache
        $cacheKey = "scoring.game_{$gameId}";
        $scores   = Cache::get($cacheKey, []);
        $prev     = $scores[$matchKey] ?? ($scores[$round] ?? []);

        $scoreA   = $request->integer('score_a', $prev['score_a'] ?? 0);
        $scoreB   = $request->integer('score_b', $prev['score_b'] ?? 0);
        $setsA    = $request->integer('sets_a', $prev['sets_a'] ?? 0);
        $setsB    = $request->integer('sets_b', $prev['sets_b'] ?? 0);
        $gamesA   = $request->integer('games_a', $prev['games_a'] ?? 0);
        $gamesB   = $request->integer('games_b', $prev['games_b'] ?? 0);
        $winnerTeam = $request->input('winner_team', $prev['winner_team'] ?? null);

        $setHistoryRaw = $request->input('set_history', $prev['set_history'] ?? []);
        if (is_string($setHistoryRaw)) {
            $setHistory = json_decode($setHistoryRaw, true) ?: [];
        } else {
            $setHistory = is_array($setHistoryRaw) ? $setHistoryRaw : [];
        }

        if ($system['is_sets']) {
            // Jika set_history kosong atau set aktif belum masuk, tambahkan set yang sedang berjalan
            $hasActiveSet = false;
            foreach ($setHistory as $sh) {
                if (($sh['set'] ?? 0) === $request->integer('set_number', 1)) {
                    $hasActiveSet = true;
                    break;
                }
            }
            if (!$hasActiveSet && ($gamesA > 0 || $gamesB > 0)) {
                $setHistory[] = [
                    'set'     => $request->integer('set_number', 1),
                    'score_a' => $gamesA,
                    'score_b' => $gamesB,
                ];
            }

            // Hitung sets dari setHistory
            if (!empty($setHistory)) {
                $calcSetsA = 0;
                $calcSetsB = 0;
                foreach ($setHistory as $s) {
                    if (($s['score_a'] ?? 0) > ($s['score_b'] ?? 0)) $calcSetsA++;
                    elseif (($s['score_b'] ?? 0) > ($s['score_a'] ?? 0)) $calcSetsB++;
                }
                if ($calcSetsA > 0 || $calcSetsB > 0) {
                    $setsA = $calcSetsA;
                    $setsB = $calcSetsB;
                }
            }
            if ($setsA === 0 && $setsB === 0 && ($gamesA > 0 || $gamesB > 0)) {
                if ($gamesA >= $gamesB) $setsA = 1;
                else $setsB = 1;
            }

            if (!$winnerTeam) {
                $winnerTeam = $setsA >= $setsB ? 'Team A' : 'Team B';
            }
        } else {
            if (!$winnerTeam) {
                $winnerTeam = $gamesA >= $gamesB ? 'Team A' : 'Team B';
            }
        }

        // 1. Simpan skor final ke Cache (shared)
        $prevVersion = (int) ($scores[$matchKey]['version'] ?? 0);
        $newVersion = $prevVersion + 1;
        $serverTimeMs = (int) round(microtime(true) * 1000);

        $scorePayload = [
            'version'         => $newVersion,
            'updated_at_ms'   => $serverTimeMs,
            'score_a'         => $system['is_sets'] ? $setsA : $gamesA,
            'score_b'         => $system['is_sets'] ? $setsB : $gamesB,
            'point_display_a' => $request->input('point_display_a', $prev['point_display_a'] ?? '0'),
            'point_display_b' => $request->input('point_display_b', $prev['point_display_b'] ?? '0'),
            'set_number'      => $request->integer('set_number', $prev['set_number'] ?? 1),
            'sets_a'          => $setsA,
            'sets_b'          => $setsB,
            'games_a'         => $gamesA,
            'games_b'         => $gamesB,
            'set_history'     => $setHistory,
            'scoring_type'    => $system['type'],
            'scoring_system'  => $scoringSystemName,
            'winner_team'     => $winnerTeam,
            'status'          => 'completed',
            'updated_at'      => now()->toDateTimeString(),
        ];

        $isMultiCourt = str_contains($matchKey, '_court_');
        $scores[$matchKey] = $scorePayload;
        if (!$isMultiCourt) {
            $scores[$round] = $scorePayload;
        }

        // Tandai game sebagai finished
        $scores['_meta'] = [
            'finished_at'    => now()->toDateTimeString(),
            'last_round_key' => $round,
            'status'         => 'finished',
        ];

        Cache::put($cacheKey, $scores, now()->addHours(4));

        // 2. Simpan permanen ke Database jika session ada di tb_session
        try {
            $session = SessionModel::with(['players', 'courts'])->find($gameId);
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

                // Reuse tb_match & participants deterministically (tidak membuat duplikat)
                $match = ScoringService::ensureMatchAndParticipants($session, $round, $courtIndex);
                if ($match) {
                    $match->status_match       = 'Completed';
                    $match->waktu_selesai      = now();
                    $match->hasil_pertandingan = $matchSummary;
                    $match->winner_team        = $winnerTeam;
                    $match->save();
                }

                if ($match) {
                    // Simpan Scores ke tb_score
                    Score::where('match_id', $match->match_id)->delete();
                    if ($system['is_sets'] && !empty($setHistory)) {
                        foreach ($setHistory as $sItem) {
                            Score::create([
                                'match_id'       => $match->match_id,
                                'set_number'     => (int) ($sItem['set'] ?? 1),
                                'game_number'    => 1,
                                'point_score_a'  => '40',
                                'point_score_b'  => '0',
                                'game_score_a'   => (int) ($sItem['score_a'] ?? 0),
                                'game_score_b'   => (int) ($sItem['score_b'] ?? 0),
                                'set_score_a'    => $setsA,
                                'set_score_b'    => $setsB,
                                'score_side_a'   => (int) ($sItem['score_a'] ?? 0),
                                'score_side_b'   => (int) ($sItem['score_b'] ?? 0),
                                'scoring_system' => $scoringSystemName,
                                'status_score'   => 'Final',
                            ]);
                        }
                    } else {
                        Score::create([
                            'match_id'       => $match->match_id,
                            'set_number'     => 1,
                            'game_number'    => 1,
                            'point_score_a'  => 'Game',
                            'point_score_b'  => '0',
                            'game_score_a'   => $gamesA,
                            'game_score_b'   => $gamesB,
                            'set_score_a'    => $setsA,
                            'set_score_b'    => $setsB,
                            'score_side_a'   => $gamesA,
                            'score_side_b'   => $gamesB,
                            'scoring_system' => $scoringSystemName,
                            'status_score'   => 'Final',
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

                $sessionPlayerMap = $session->players->keyBy(fn($p) => ScoringService::cleanPlayerName($p->nama));

                $isTeamAWinner = ($winnerTeam === 'Team A');

                // Tentukan apakah ini Single (1 pemain per sisi) atau Double (2+ pemain per sisi)
                $isSingleMatch = (count($teamAPlayers) === 1 && count($teamBPlayers) === 1);

                // Resolve player objects dari nama
                $resolvePlayer = function (string $name) use ($sessionPlayerMap): ?object {
                    return $sessionPlayerMap->get(ScoringService::cleanPlayerName($name));
                };

                foreach ($teamAPlayers as $pName) {
                    $player = $resolvePlayer($pName);
                    if (!$player) continue;

                    if ($isSingleMatch) {
                        // Single: tidak ada partner; opponent adalah satu-satunya pemain di sisi B
                        $partnerPlayerId   = null;
                        $opponentPlayerObj = $resolvePlayer($teamBPlayers[0] ?? '');
                        $opponentPlayerId  = $opponentPlayerObj?->player_id;
                    } else {
                        // Double: partner adalah rekan setim di sisi A (selain diri sendiri)
                        $partnerName       = collect($teamAPlayers)->first(fn($n) => ScoringService::cleanPlayerName($n) !== ScoringService::cleanPlayerName($pName));
                        $partnerPlayerObj  = $partnerName ? $resolvePlayer($partnerName) : null;
                        $partnerPlayerId   = $partnerPlayerObj?->player_id;
                        // Opponent: pemain pertama di sisi B yang ada di DB
                        $opponentPlayerObj = $resolvePlayer($teamBPlayers[0] ?? '');
                        $opponentPlayerId  = $opponentPlayerObj?->player_id;
                    }

                    PlayingHistory::updateOrCreate(
                        [
                            'player_id' => $player->player_id,
                            'match_id'  => $match->match_id,
                        ],
                        [
                            'jumlah_permainan'   => max(1, $totalGamesPlayed),
                            'waktu_permainan'    => now(),
                            'status_permainan'   => $isTeamAWinner ? 'Menang' : 'Kalah',
                            'partner_player_id'  => $partnerPlayerId,
                            'opponent_player_id' => $opponentPlayerId,
                        ]
                    );
                }

                foreach ($teamBPlayers as $pName) {
                    $player = $resolvePlayer($pName);
                    if (!$player) continue;

                    if ($isSingleMatch) {
                        // Single: tidak ada partner; opponent adalah satu-satunya pemain di sisi A
                        $partnerPlayerId   = null;
                        $opponentPlayerObj = $resolvePlayer($teamAPlayers[0] ?? '');
                        $opponentPlayerId  = $opponentPlayerObj?->player_id;
                    } else {
                        // Double: partner adalah rekan setim di sisi B (selain diri sendiri)
                        $partnerName       = collect($teamBPlayers)->first(fn($n) => ScoringService::cleanPlayerName($n) !== ScoringService::cleanPlayerName($pName));
                        $partnerPlayerObj  = $partnerName ? $resolvePlayer($partnerName) : null;
                        $partnerPlayerId   = $partnerPlayerObj?->player_id;
                        // Opponent: pemain pertama di sisi A yang ada di DB
                        $opponentPlayerObj = $resolvePlayer($teamAPlayers[0] ?? '');
                        $opponentPlayerId  = $opponentPlayerObj?->player_id;
                    }

                    PlayingHistory::updateOrCreate(
                        [
                            'player_id' => $player->player_id,
                            'match_id'  => $match->match_id,
                        ],
                        [
                            'jumlah_permainan'   => max(1, $totalGamesPlayed),
                            'waktu_permainan'    => now(),
                            'status_permainan'   => !$isTeamAWinner ? 'Menang' : 'Kalah',
                            'partner_player_id'  => $partnerPlayerId,
                            'opponent_player_id' => $opponentPlayerId,
                        ]
                    );
                }
            }
        }
    } catch (\Throwable $e) {
            // Log warning, don't crash redirect
            \Illuminate\Support\Facades\Log::warning("Failed to persist score to database: {$e->getMessage()}");
        }

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
        $scoringSystem = ScoringService::detectScoringSystem($game['scoring_system'] ?? '');

        // Ambil skor tersimpan dari Cache (shared)
        $cacheKey    = "scoring.game_{$game['id']}";
        $savedScores = Cache::get($cacheKey, []);

        // Fallback Database: Jika Cache kosong atau tidak ada ronde yang berstatus completed, coba muat dari database
        $hasCompletedInCache = false;
        foreach ($savedScores as $k => $v) {
            if ($k !== '_meta' && is_array($v) && ($v['status'] ?? '') === 'completed') {
                $hasCompletedInCache = true;
                break;
            }
        }

        if (!$hasCompletedInCache) {
            try {
                $drawingRecord = Drawing::where('session_id', $game['id'])->first();
                if ($drawingRecord) {
                    $matches = GameMatch::with('scores')->where('drawing_id', $drawingRecord->drawing_id)->get();
                    $dbScoresRecovered = false;

                    foreach ($matches as $match) {
                        $rKey = "round_{$match->nomor_match}";
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
                                    'set'     => (int) $sc->set_number,
                                    'score_a' => (int) $sc->score_side_a,
                                    'score_b' => (int) $sc->score_side_b,
                                ];
                            }
                        }

                        if ($matchScores->isNotEmpty() || $match->status_match === 'Completed') {
                            $dbScoresRecovered = true;
                            $savedScores[$rKey] = [
                                'score_a'         => $scoringSystem['is_sets'] ? $mSetsA : $mGamesA,
                                'score_b'         => $scoringSystem['is_sets'] ? $mSetsB : $mGamesB,
                                'point_display_a' => '0',
                                'point_display_b' => '0',
                                'set_number'      => max(1, count($mSetHistory)),
                                'sets_a'          => $mSetsA,
                                'sets_b'          => $mSetsB,
                                'games_a'         => $mGamesA,
                                'games_b'         => $mGamesB,
                                'set_history'     => $mSetHistory,
                                'scoring_type'    => $scoringSystem['type'],
                                'scoring_system'  => $scoringSystem['label'],
                                'winner_team'     => $match->winner_team ?: ($mGamesA >= $mGamesB ? 'Team A' : 'Team B'),
                                'status'          => 'completed',
                                'updated_at'      => $match->updated_at ? $match->updated_at->toDateTimeString() : now()->toDateTimeString(),
                            ];
                            $savedScores['_meta'] = [
                                'finished_at'    => $match->updated_at ? $match->updated_at->toDateTimeString() : now()->toDateTimeString(),
                                'last_round_key' => $rKey,
                                'status'         => 'finished',
                            ];
                        }
                    }

                    if ($dbScoresRecovered) {
                        Cache::put($cacheKey, $savedScores, now()->addHours(4));
                    }
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Failed to recover scores from DB in recap: {$e->getMessage()}");
            }
        }

        // Sinkronisasi effective round scores
        $effectiveScores = ScoringService::getEffectiveScores($game, $savedScores);

        // Hitung ranking semua pemain
        $rankedPlayers = ScoringService::calculateRecap($game, $effectiveScores);

        // Tentukan winner & scoreboard match yang ditampilkan
        $lastRoundKey  = null;
        $lastScore     = null;
        $drawing       = $game['drawing'] ?? [];

        if (!empty($drawing)) {
            $targetRoundKey = null;

            if (!empty($savedScores['_meta']['last_round_key']) && isset($drawing[$savedScores['_meta']['last_round_key']])) {
                $targetRoundKey = $savedScores['_meta']['last_round_key'];
            }

            if (!$targetRoundKey) {
                foreach ($drawing as $rKey => $rData) {
                    if (isset($savedScores[$rKey]) && ($savedScores[$rKey]['status'] ?? '') === 'completed') {
                        $targetRoundKey = $rKey;
                    }
                }
            }

            if (!$targetRoundKey) {
                foreach ($drawing as $rKey => $rData) {
                    if (isset($effectiveScores[$rKey]) && ($effectiveScores[$rKey]['status'] ?? '') === 'completed') {
                        $targetRoundKey = $rKey;
                        break;
                    }
                }
            }

            if (!$targetRoundKey) {
                $targetRoundKey = array_key_first($drawing);
            }

            $lastRoundKey = $targetRoundKey;
            $lastRound    = $drawing[$lastRoundKey] ?? [];

            if (isset($savedScores[$lastRoundKey]) && ($savedScores[$lastRoundKey]['status'] ?? '') === 'completed') {
                $lastScore = $savedScores[$lastRoundKey];
            } else {
                $lastScore = $effectiveScores[$lastRoundKey] ?? null;
            }

            if (!$lastScore) {
                if ($scoringSystem['is_sets']) {
                    $lastScore = [
                        'score_a'     => 2,
                        'score_b'     => 1,
                        'sets_a'      => 2,
                        'sets_b'      => 1,
                        'games_a'     => 14,
                        'games_b'     => 11,
                        'set_history' => [
                            ['set' => 1, 'score_a' => 6, 'score_b' => 3],
                            ['set' => 2, 'score_a' => 4, 'score_b' => 6],
                            ['set' => 3, 'score_a' => 6, 'score_b' => 2],
                        ],
                        'status'      => 'completed',
                    ];
                } else {
                    $target = $scoringSystem['target_games'] ?? 8;
                    $lastScore = [
                        'score_a'     => $target,
                        'score_b'     => (int) ($target * 0.6),
                        'sets_a'      => 1,
                        'sets_b'      => 0,
                        'games_a'     => $target,
                        'games_b'     => (int) ($target * 0.6),
                        'set_history' => [],
                        'status'      => 'completed',
                    ];
                }
            }

            if ($scoringSystem['is_sets'] && $lastScore) {
                $sHist = $lastScore['set_history'] ?? [];
                $sA    = (int) ($lastScore['sets_a'] ?? 0);
                $sB    = (int) ($lastScore['sets_b'] ?? 0);
                $gA    = (int) ($lastScore['games_a'] ?? 0);
                $gB    = (int) ($lastScore['games_b'] ?? 0);

                if (empty($sHist)) {
                    if ($gA > 0 || $gB > 0) {
                        $sHist = [
                            ['set' => 1, 'score_a' => $gA, 'score_b' => $gB]
                        ];
                        if ($sA === 0 && $sB === 0) {
                            $sA = $gA >= $gB ? 1 : 0;
                            $sB = $gB > $gA ? 1 : 0;
                        }
                    } elseif ($sA > 0 || $sB > 0) {
                        $sHist = [];
                        for ($i = 1; $i <= ($sA + $sB); $i++) {
                            $aWins = ($i <= $sA);
                            $sHist[] = [
                                'set'     => $i,
                                'score_a' => $aWins ? 6 : 3,
                                'score_b' => $aWins ? 3 : 6,
                            ];
                        }
                    } else {
                        $sA = 2;
                        $sB = 1;
                        $sHist = [
                            ['set' => 1, 'score_a' => 6, 'score_b' => 3],
                            ['set' => 2, 'score_a' => 4, 'score_b' => 6],
                            ['set' => 3, 'score_a' => 6, 'score_b' => 2],
                        ];
                    }
                    $lastScore['set_history'] = $sHist;
                    $lastScore['sets_a'] = $sA;
                    $lastScore['sets_b'] = $sB;
                    $lastScore['score_a'] = $sA;
                    $lastScore['score_b'] = $sB;
                }
            }

            $lastScore['team_a'] = $lastRound['team_a'] ?? [];
            $lastScore['team_b'] = $lastRound['team_b'] ?? [];
            $lastScore['round_title'] = ucfirst(str_replace('_', ' ', $lastRoundKey));
        }

        // Data untuk player recap card
        $playerRecap = MatchaDummyDataService::getPlayerRecap();

        return view('scoring.recap', compact(
            'game',
            'scoringSystem',
            'rankedPlayers',
            'lastScore',
            'playerRecap',
            'savedScores',
            'effectiveScores'
        ));
    }

    private function getGameData($id)
    {
        try {
            $dbSession = SessionModel::with(['sport', 'venue', 'courts', 'players', 'host'])->find((int) $id);
        } catch (\Throwable $e) {
            $dbSession = null;
        }

        if ($dbSession) {
            $participants = $dbSession->players->map(function ($p) {
                return [
                    'id' => $p->player_id,
                    'name' => $p->nama,
                    'gender' => $p->gender ?? 'Male',
                    'age' => $p->usia ?? 25,
                    'level' => $p->level ?? 'Intermediate',
                    'is_member' => !empty($p->user_id),
                    'phone' => $p->no_hp,
                    'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80',
                ];
            })->toArray();

            // Baca jenis_permainan dari session (Single/Double) untuk threshold dummy
            $sessionJenisPermainan = $dbSession->jenis_permainan ?? 'Double';
            $isSingleSession       = strtolower($sessionJenisPermainan) === 'single';
            $minParticipants       = $isSingleSession ? 2 : 4;

            // Lengkapi jika kurang dari minimum
            if (count($participants) < $minParticipants) {
                $dummy        = MatchaDummyDataService::getGames()[0]['participants'];
                $participants = array_merge($participants, array_slice($dummy, count($participants)));
            }

            $format = strtolower(request('format', ''));
            if (empty($format)) {
                $dbDrawing = Drawing::where('session_id', $dbSession->session_id)->with('matchFormat')->first();
                $format = strtolower($dbDrawing->matchFormat->nama_format ?? 'americano');
            }
            $courtCount = max(1, $dbSession->courts->count());

            $cachedSchedule = Cache::get("drawing.schedule_{$dbSession->session_id}");
            if ($cachedSchedule && !empty($cachedSchedule['rounds'])) {
                $rounds = $cachedSchedule['rounds'];
                $courtCount = $cachedSchedule['court_count'] ?? $courtCount;
            } else {
                try {
                    if (str_contains($format, 'team') && count($participants) >= 4 && count($participants) % 2 === 0) {
                        $teamService = new \App\Services\Drawing\TeamAmericanoService();
                        $drawingData = $teamService->generateTeamRounds($participants, $courtCount);
                        $rounds = $drawingData['rounds'] ?? [];
                    } else {
                        // Gunakan jenis_permainan dari session agar Single/Double benar
                        $americanoService = new \App\Services\Drawing\AmericanoService();
                        $rounds = $americanoService->generateRounds($participants, $courtCount, null, $sessionJenisPermainan ?? 'Double');
                    }
                } catch (\Throwable $e) {
                    $americanoService = new \App\Services\Drawing\AmericanoService();
                    $rounds = $americanoService->generateRounds($participants, $courtCount);
                }
            }

            $drawingMap = [];
            foreach ($rounds as $rNum => $rData) {
                $drawingMap["round_{$rNum}"] = [
                    'round_number' => $rNum,
                    'team_a'       => $rData['teamA'] ?? ($rData['team_a'] ?? []),
                    'team_b'       => $rData['teamB'] ?? ($rData['team_b'] ?? []),
                    'team_a_names' => $rData['teamA_names'] ?? ($rData['team_a_names'] ?? []),
                    'team_b_names' => $rData['teamB_names'] ?? ($rData['team_b_names'] ?? []),
                    'resting'      => $rData['resting'] ?? [],
                    'matches'      => $rData['matches'] ?? [],
                    'court_count'  => $courtCount,
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
                'time' => $dbSession->waktu_session ?? '18:30 WIB',
                'duration' => '2 Jam',
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
                    'phone' => $dbSession->host->no_hp ?? '-',
                    'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80',
                ],
                'participants' => $participants,
                'drawing' => $drawingMap,
            ];
        }

        $games = MatchaDummyDataService::getGames();
        $dummyGame = collect($games)->firstWhere('id', (int) $id) ?? $games[0];
        $cachedSchedule = Cache::get("drawing.schedule_{$id}");
        if ($cachedSchedule && !empty($cachedSchedule['rounds'])) {
            $drawingMap = [];
            foreach ($cachedSchedule['rounds'] as $rNum => $rData) {
                $drawingMap["round_{$rNum}"] = [
                    'round_number' => $rNum,
                    'team_a'       => $rData['teamA'] ?? ($rData['team_a'] ?? []),
                    'team_b'       => $rData['teamB'] ?? ($rData['team_b'] ?? []),
                    'team_a_names' => $rData['teamA_names'] ?? ($rData['team_a_names'] ?? []),
                    'team_b_names' => $rData['teamB_names'] ?? ($rData['team_b_names'] ?? []),
                    'resting'      => $rData['resting'] ?? [],
                    'matches'      => $rData['matches'] ?? [],
                    'court_count'  => $cachedSchedule['court_count'] ?? 1,
                ];
            }
            $dummyGame['drawing'] = $drawingMap;
        }
        return $dummyGame;
    }

    /**
     * Ambil skor dari database (tb_match & tb_score) untuk round & court tertentu.
     */
    protected function getScoreFromDatabase($gameId, $round, $courtIndex = 0)
    {
        try {
            $drawing = Drawing::where('session_id', (int) $gameId)->first();
            if (!$drawing) {
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

            if (!$match) {
                return null;
            }

            $scores = Score::where('match_id', $match->match_id)->orderBy('set_number', 'asc')->get();
            if ($scores->isEmpty() && strtolower($match->status_match) !== 'completed') {
                return null;
            }

            $lastScore = $scores->last();
            $setHistory = [];
            $setsA = 0;
            $setsB = 0;
            $gamesA = 0;
            $gamesB = 0;

            foreach ($scores as $sc) {
                $setsA = max($setsA, (int) $sc->set_score_a);
                $setsB = max($setsB, (int) $sc->set_score_b);
                $gamesA = (int) $sc->game_score_a;
                $gamesB = (int) $sc->game_score_b;
                $setHistory[] = [
                    'set'     => (int) $sc->set_number,
                    'score_a' => (int) $sc->score_side_a,
                    'score_b' => (int) $sc->score_side_b,
                ];
            }

            if ($lastScore) {
                $gamesA = (int) $lastScore->game_score_a;
                $gamesB = (int) $lastScore->game_score_b;
            }

            $status = strtolower($match->status_match) === 'completed' ? 'completed' : 'in_progress';

            return [
                'match_id'        => $match->match_id,
                'version'         => 1,
                'updated_at_ms'   => $match->updated_at ? (int) round($match->updated_at->timestamp * 1000) : (int) round(microtime(true) * 1000),
                'score_a'         => $gamesA,
                'score_b'         => $gamesB,
                'point_display_a' => (string) ($lastScore->point_score_a ?? '0'),
                'point_display_b' => (string) ($lastScore->point_score_b ?? '0'),
                'set_number'      => (int) ($lastScore->set_number ?? 1),
                'sets_a'          => $setsA,
                'sets_b'          => $setsB,
                'games_a'         => $gamesA,
                'games_b'         => $gamesB,
                'set_history'     => $setHistory,
                'idx_a'           => 0,
                'idx_b'           => 0,
                'is_deuce'        => false,
                'advantage'       => null,
                'scoring_type'    => $lastScore->scoring_system ?? 'total_of_sets',
                'status'          => $status,
                'winner_team'     => $match->winner_team,
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Failed to get score from database: " . $e->getMessage());
            return null;
        }
    }
}
