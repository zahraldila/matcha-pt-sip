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

        // Tentukan round aktif (bisa dari query param atau default round_1)
        $activeRound = request('round', 'round_1');

        // Bangun konteks match (tim A, tim B, istirahat) untuk round aktif
        $matchContext = ScoringService::buildMatchContext($game, $activeRound);

        // Deteksi scoring system
        $scoringSystem = ScoringService::detectScoringSystem($game['scoring_system'] ?? '');

        // Ambil skor tersimpan dari Cache (shared antar semua user)
        $cacheKey    = "scoring.game_{$game['id']}";
        $savedScores = Cache::get($cacheKey, []);
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
        $currentScore = array_merge($defaultScore, $savedScores[$activeRound] ?? []);

        // Deteksi role: hanya 'host' yang bisa mengedit skor
        $userRole = Auth::check() ? Auth::user()->role : 'guest';
        $isHost   = ($userRole === 'host');

        return view('scoring.live', compact(
            'game',
            'matchContext',
            'scoringSystem',
            'currentScore',
            'activeRound',
            'savedScores',
            'isHost',
            'userRole',
            'cacheKey'
        ));
    }

    /**
     * JSON endpoint: ambil skor terkini dari Cache.
     * Dipakai oleh member untuk polling realtime via fetch().
     */
    public function getScore($gameId, $round = 'round_1')
    {
        $cacheKey = "scoring.game_{$gameId}";
        $scores   = Cache::get($cacheKey, []);
        $score    = $scores[$round] ?? [];

        return response()->json([
            'game_id'         => (int) $gameId,
            'round'           => $round,
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
        ]);
    }

    /**
     * Simpan/update skor pertandingan (dipanggil via AJAX fetch dari live view).
     * Menyimpan ke Laravel Cache.
     */
    public function updateScore(Request $request)
    {
        // Hanya host yang boleh update skor
        if (!Auth::check() || Auth::user()->role !== 'host') {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Hanya Host yang dapat mencatat skor.',
            ], 403);
        }

        $request->validate([
            'game_id'         => 'required|integer',
            'round'           => 'required|string',
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

        $gameId = $request->integer('game_id');
        $round  = $request->string('round')->toString();
        $status = $request->input('status', 'in_progress');

        // Simpan ke Cache (TTL 4 jam) agar bisa dibaca semua user
        $cacheKey = "scoring.game_{$gameId}";
        $scores   = Cache::get($cacheKey, []);

        $scores[$round] = [
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

        Cache::put($cacheKey, $scores, now()->addHours(4));

        return response()->json([
            'success' => true,
            'message' => 'Skor berhasil disimpan.',
            'saved'   => $scores[$round],
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

        $game = $this->getGameData($gameId);
        $scoringSystemName = $request->input('scoring_system', $game['scoring_system'] ?? 'Total of 3');
        $system = ScoringService::detectScoringSystem($scoringSystemName);

        // Ambil data skor dari request atau fallback ke cache
        $cacheKey = "scoring.game_{$gameId}";
        $scores   = Cache::get($cacheKey, []);
        $prev     = $scores[$round] ?? [];

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
        $scores[$round] = [
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

                // Pastikan Drawing ada
                $drawing = Drawing::firstOrCreate(
                    ['session_id' => $session->session_id],
                    [
                        'match_format_id' => 1,
                        'tanggal_drawing' => now()->toDateString(),
                        'jam_drawing'     => now()->format('H:i:s'),
                    ]
                );

                preg_match('/(\d+)/', $round, $roundNumMatch);
                $matchNum = isset($roundNumMatch[1]) ? (int) $roundNumMatch[1] : 1;
                $courtId = $session->courts->first()->court_id ?? null;

                $matchSummary = $system['is_sets']
                    ? "Set Score {$setsA} - {$setsB}"
                    : "Game Score {$gamesA} - {$gamesB}";

                // Buat / Update tb_match
                $match = GameMatch::updateOrCreate(
                    [
                        'drawing_id'  => $drawing->drawing_id,
                        'nomor_match' => $matchNum,
                    ],
                    [
                        'court_id'           => $courtId,
                        'status_match'       => 'Completed',
                        'waktu_mulai'        => now()->subMinutes(30),
                        'waktu_selesai'      => now(),
                        'hasil_pertandingan' => $matchSummary,
                        'winner_team'        => $winnerTeam,
                    ]
                );

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
                $matchContext = ScoringService::buildMatchContext($game, $round);
                $teamAPlayers = $matchContext['team_a'] ?? [];
                $teamBPlayers = $matchContext['team_b'] ?? [];

                $totalGamesPlayed = $system['is_sets']
                    ? array_sum(array_column($setHistory, 'score_a')) + array_sum(array_column($setHistory, 'score_b'))
                    : ($gamesA + $gamesB);

                $sessionPlayerMap = $session->players->keyBy(fn($p) => ScoringService::cleanPlayerName($p->nama));

                $isTeamAWinner = ($winnerTeam === 'Team A');

                foreach ($teamAPlayers as $pName) {
                    $cleaned = ScoringService::cleanPlayerName($pName);
                    $player = $sessionPlayerMap->get($cleaned);
                    if (!$player) continue;

                    PlayingHistory::updateOrCreate(
                        [
                            'player_id' => $player->player_id,
                            'match_id'  => $match->match_id,
                        ],
                        [
                            'jumlah_permainan'   => max(1, $totalGamesPlayed),
                            'waktu_permainan'    => now(),
                            'status_permainan'   => $isTeamAWinner ? 'Menang' : 'Kalah',
                            'partner_player_id'  => null,
                            'opponent_player_id' => null,
                        ]
                    );
                }

                foreach ($teamBPlayers as $pName) {
                    $cleaned = ScoringService::cleanPlayerName($pName);
                    $player = $sessionPlayerMap->get($cleaned);
                    if (!$player) continue;

                    PlayingHistory::updateOrCreate(
                        [
                            'player_id' => $player->player_id,
                            'match_id'  => $match->match_id,
                        ],
                        [
                            'jumlah_permainan'   => max(1, $totalGamesPlayed),
                            'waktu_permainan'    => now(),
                            'status_permainan'   => !$isTeamAWinner ? 'Menang' : 'Kalah',
                            'partner_player_id'  => null,
                            'opponent_player_id' => null,
                        ]
                    );
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

            // Lengkapi jika kurang dari 4
            if (count($participants) < 4) {
                $dummy = MatchaDummyDataService::getGames()[0]['participants'];
                $participants = array_merge($participants, array_slice($dummy, count($participants)));
            }

            $format = strtolower(request('format', 'americano'));
            $courtCount = max(1, $dbSession->courts->count());

            try {
                if (str_contains($format, 'team') && count($participants) >= 4 && count($participants) % 2 === 0) {
                    $teamService = new \App\Services\Drawing\TeamAmericanoService();
                    $drawingData = $teamService->generateTeamRounds($participants, $courtCount);
                    $rounds = $drawingData['rounds'] ?? [];
                } else {
                    $americanoService = new \App\Services\Drawing\AmericanoService();
                    $rounds = $americanoService->generateRounds($participants, $courtCount);
                }
            } catch (\Throwable $e) {
                $americanoService = new \App\Services\Drawing\AmericanoService();
                $rounds = $americanoService->generateRounds($participants, $courtCount);
            }

            $drawingMap = [];
            foreach ($rounds as $rNum => $rData) {
                $drawingMap["round_{$rNum}"] = [
                    'team_a' => $rData['teamA'] ?? [],
                    'team_b' => $rData['teamB'] ?? [],
                    'resting' => $rData['resting'] ?? [],
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
        return collect($games)->firstWhere('id', (int) $id) ?? $games[0];
    }
}
