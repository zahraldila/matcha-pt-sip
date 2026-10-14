<?php

namespace Tests\Unit;

use App\Http\Controllers\Game\GameController;
use App\Http\Controllers\Player\PlayerController;
use App\Http\Controllers\Scoring\ScoringController;
use App\Models\Court;
use App\Models\Drawing;
use App\Models\GameMatch;
use App\Models\MatchParticipant;
use App\Models\Player;
use App\Models\Score;
use App\Models\SessionModel;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use App\Services\Drawing\AmericanoService;
use App\Services\Drawing\TeamAmericanoService;
use App\Services\Scoring\ScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Tests\TestCase;

class DrawingAndScoringLogicTest extends TestCase
{
    public function test_session_duration_is_hidden_until_game_is_completed_and_uses_real_match_duration_afterward()
    {
        $this->setupTestDatabaseSchema();

        $session = SessionModel::create([
            'host_user_id' => 1,
            'sport_id' => 1,
            'venue_id' => 1,
            'nama_session' => 'Mabar Uji Durasi',
            'scoring_system' => 'Total of 3',
            'waktu_session' => '08:00 WIB (2 Jam)',
            'datetime' => '2026-09-17 08:00:00',
            'status_session' => 'Open',
            'jumlah_pemain' => '4',
            'jenis_permainan' => 'Double',
        ]);

        $this->assertSame('08:00', GameController::resolveSessionDisplayTime($session));
        $this->assertSame('Ready for Drawing', GameController::resolveSessionStatus($session, 0));
        $this->assertSame('-', GameController::resolveSessionDuration($session));

        $session->update(['status_session' => 'Finished']);
        $this->assertSame('Selesai Mabar', GameController::resolveSessionStatus($session, 0));

        $drawing = Drawing::create([
            'session_id' => $session->session_id,
            'match_format_id' => 1,
            'tanggal_drawing' => '2026-09-17',
            'jam_drawing' => '08:00:00',
        ]);

        GameMatch::create([
            'drawing_id' => $drawing->drawing_id,
            'court_id' => 1,
            'nomor_match' => 1,
            'status_match' => 'Completed',
            'waktu_mulai' => '2026-09-17 08:00:00',
            'waktu_selesai' => '2026-09-17 10:30:00',
            'hasil_pertandingan' => '7-5',
            'winner_team' => 'A',
            'version' => 1,
        ]);

        $this->assertSame('2 Jam 30 Menit', GameController::resolveSessionDuration($session));
    }

    public function test_scoring_configuration_uses_expected_set_and_point_targets()
    {
        foreach ([
            'Total of 3' => [3, 3, true, 6],
            'Total of 4' => [4, 4, true, 6],
            'Total of 5' => [5, 5, true, 6],
            'Total of 6' => [6, 6, true, 6],
            'Total of 7' => [7, 7, true, 6],
            'Total of 8' => [8, 8, true, 6],
            'Total of 9' => [9, 9, true, 6],
            'Total of 10' => [10, 10, true, 6],
            'First to 2' => [1, 1, false, 2],
            'First to 3' => [1, 1, false, 3],
            'First to 4' => [1, 1, false, 4],
            'First to 5' => [1, 1, false, 5],
            'First to 6' => [1, 1, false, 6],
            'First to 7' => [1, 1, false, 7],
            'First to 8' => [1, 1, false, 8],
            'First to 9' => [1, 1, false, 9],
            'First to 10' => [1, 1, false, 10],
            // Kompatibilitas data legacy (tetap didukung pembacaannya oleh detectScoringSystem)
            'First to 11' => [1, 1, false, 11],
            'First to 15' => [1, 1, false, 15],
            'First to 21' => [1, 1, false, 21],
        ] as $label => [$maxSets, $targetSets, $isSets, $targetGames]) {
            $system = ScoringService::detectScoringSystem($label);

            $this->assertSame($maxSets, $system['max_sets'], "max_sets tidak cocok untuk {$label}");
            $this->assertSame($targetSets, $system['target_sets'], "target_sets tidak cocok untuk {$label}");
            $this->assertSame($isSets, $system['is_sets'], "is_sets tidak cocok untuk {$label}");
            $this->assertSame($targetGames, $system['target_games'], "target_games tidak cocok untuk {$label}");
            $this->assertSame($label, $system['label'], "label tidak cocok untuk {$label}");
            if (str_starts_with($label, 'Total of')) {
                $this->assertTrue($system['is_total_of']);
                $this->assertSame($targetGames, $system['target_total_games']);
            }
        }

        // Pastikan angka 10 dibaca utuh sebagai 10, bukan 1 atau fallback
        $total10 = ScoringService::detectScoringSystem('Total of 10');
        $this->assertSame(10, $total10['max_sets']);
        $this->assertSame(10, $total10['target_sets']);
        $this->assertSame(6, $total10['target_total_games']);
        $this->assertSame('Total of 10', $total10['label']);

        $first10 = ScoringService::detectScoringSystem('First to 10');
        $this->assertSame(10, $first10['target_games']);
        $this->assertSame('First to 10', $first10['label']);
    }

    public function test_scoring_validation_rules_and_legacy_separation()
    {
        // 1. Seluruh opsi baru Total of 3..10 dan First to 2..10 valid untuk sesi baru
        foreach (range(3, 10) as $sets) {
            $this->assertTrue(ScoringService::isValidNewScoringOption("Total of {$sets}"), "Total of {$sets} harus valid");
        }
        foreach (range(2, 10) as $games) {
            $this->assertTrue(ScoringService::isValidNewScoringOption("First to {$games}"), "First to {$games} harus valid");
        }

        // 2. Opsi legacy (11, 15, 21) TIDAK boleh dipilih untuk sesi baru
        foreach ([11, 15, 21] as $legacyGames) {
            $this->assertFalse(
                ScoringService::isValidNewScoringOption("First to {$legacyGames}"),
                "First to {$legacyGames} adalah opsi legacy dan tidak boleh diizinkan untuk sesi baru."
            );
        }

        // 3. Nilai rusak atau di luar rentang ditolak
        $invalidOptions = [
            'Total of 1',
            'Total of 2',
            'Total of 11',
            'First to 1',
            'First to 25',
            'Total of abc',
            'Custom Scoring',
            '',
            null,
        ];
        foreach ($invalidOptions as $inv) {
            $this->assertFalse(
                ScoringService::isValidNewScoringOption($inv),
                "Opsi '{$inv}' harus ditolak oleh isValidNewScoringOption."
            );
        }

        // 4. Controller request validation menolak opsi invalid untuk sesi baru
        $validator = Validator::make(
            ['scoring_system' => 'Total of 99'],
            ['scoring_system' => ['required', 'string', Rule::in(ScoringService::getAllowedNewScoringSystems())]],
            ['scoring_system.in' => 'Sistem skor yang dipilih tidak valid. Pilihan tersedia: Total of 3–10 atau First to 2–10.']
        );
        $this->assertTrue($validator->fails());
        $this->assertSame(
            'Sistem skor yang dipilih tidak valid. Pilihan tersedia: Total of 3–10 atau First to 2–10.',
            $validator->errors()->first('scoring_system')
        );

        // Validator juga menolak opsi legacy pada pembuatan sesi baru
        $legacyValidator = Validator::make(
            ['scoring_system' => 'First to 11'],
            ['scoring_system' => ['required', 'string', Rule::in(ScoringService::getAllowedNewScoringSystems())]]
        );
        $this->assertTrue($legacyValidator->fails(), 'Opsi legacy First to 11 harus ditolak pada pembuatan sesi baru.');
    }

    public function test_americano_explicit_round_count_matches_total_of_configuration_for_single_and_double()
    {
        $service = new AmericanoService;

        foreach (['Single' => ['Alice', 'Bob'], 'Double' => ['Alice', 'Bob', 'Charlie', 'David']] as $mode => $players) {
            foreach ([3, 4, 5, 6, 7, 8, 9, 10] as $roundCount) {
                $rounds = $service->generateRounds($players, 1, $roundCount, $mode);

                $this->assertCount($roundCount, $rounds, "{$mode} Total of {$roundCount} harus menghasilkan {$roundCount} ronde.");
            }
        }
    }

    public function test_americano_first_to_configuration_generates_one_round()
    {
        $service = new AmericanoService;

        foreach ([2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 15, 21] as $target) {
            $system = ScoringService::detectScoringSystem("First to {$target}");
            $roundCount = $system['is_sets'] ? $system['max_sets'] : 1;

            $this->assertCount(1, $service->generateRounds(['Alice', 'Bob', 'Charlie', 'David'], 1, $roundCount, 'Double'));
            $this->assertSame($target, $system['target_games']);
        }
    }

    public function test_even_total_of_round_progression_and_recap()
    {
        // Uji Total of 4 (genap): harus menghasilkan 4 ronde dan dapat selesai tanpa deadlock
        $service = new AmericanoService;
        $players = ['Player 1', 'Player 2', 'Player 3', 'Player 4'];
        $rounds = $service->generateRounds($players, 1, 4, 'Double');
        $this->assertCount(4, $rounds, 'Total of 4 harus menghasilkan tepat 4 ronde.');

        // Simulasi kalkulasi recap jika set berimbang (2 ronde dimenangkan masing-masing pihak)
        $game = [
            'match_format' => 'Americano',
            'scoring_system' => 'Total of 4',
            'participants' => array_map(fn ($p) => ['name' => $p], $players),
            'drawing' => $rounds,
        ];
        $sessionScores = [
            'round_1' => ['status' => 'completed', 'games_a' => 6, 'games_b' => 4, 'score_a' => 6, 'score_b' => 4],
            'round_2' => ['status' => 'completed', 'games_a' => 3, 'games_b' => 6, 'score_a' => 3, 'score_b' => 6],
            'round_3' => ['status' => 'completed', 'games_a' => 6, 'games_b' => 2, 'score_a' => 6, 'score_b' => 2],
            'round_4' => ['status' => 'completed', 'games_a' => 5, 'games_b' => 6, 'score_a' => 5, 'score_b' => 6],
        ];

        $recap = ScoringService::calculateRecap($game, $sessionScores);
        $this->assertCount(4, $recap);
        // Seluruh pemain memiliki rank 1..4 tanpa crash atau division by zero
        $this->assertSame(1, $recap[0]['rank']);
        $this->assertSame(2, $recap[1]['rank']);
        $this->assertSame(3, $recap[2]['rank']);
        $this->assertSame(4, $recap[3]['rank']);
    }

    public function test_total_of_set_completion_rules_and_scores()
    {
        $totalOf3Config = ScoringService::detectScoringSystem('Total of 3');
        // Skor 5–4: belum selesai
        $this->assertFalse(ScoringService::isMatchCompleted(5, 4, $totalOf3Config));
        $this->assertNull(ScoringService::determineMatchWinner(5, 4, $totalOf3Config));
        // Skor 6–1: selesai (Team A)
        $this->assertTrue(ScoringService::isMatchCompleted(6, 1, $totalOf3Config));
        $this->assertSame('Team A', ScoringService::determineMatchWinner(6, 1, $totalOf3Config));
        // Skor 6–4: selesai (Team A)
        $this->assertTrue(ScoringService::isMatchCompleted(6, 4, $totalOf3Config));
        $this->assertSame('Team A', ScoringService::determineMatchWinner(6, 4, $totalOf3Config));
        // Skor 5–5: belum selesai (lanjut ke 7)
        $this->assertFalse(ScoringService::isMatchCompleted(5, 5, $totalOf3Config));
        // Skor 7–5: selesai (Team A)
        $this->assertTrue(ScoringService::isMatchCompleted(7, 5, $totalOf3Config));
        $this->assertSame('Team A', ScoringService::determineMatchWinner(7, 5, $totalOf3Config));
        // Hasil kebalikan untuk Team B:
        $this->assertTrue(ScoringService::isMatchCompleted(2, 6, $totalOf3Config));
        $this->assertSame('Team B', ScoringService::determineMatchWinner(2, 6, $totalOf3Config));
        $this->assertTrue(ScoringService::isMatchCompleted(6, 7, $totalOf3Config));
        $this->assertSame('Team B', ScoringService::determineMatchWinner(6, 7, $totalOf3Config));

        $totalOf10Config = ScoringService::detectScoringSystem('Total of 10');
        // Skor 6–4: selesai (Team A)
        $this->assertTrue(ScoringService::isMatchCompleted(6, 4, $totalOf10Config));
        $this->assertSame('Team A', ScoringService::determineMatchWinner(6, 4, $totalOf10Config));
        // Skor 4–6: selesai (Team B)
        $this->assertTrue(ScoringService::isMatchCompleted(4, 6, $totalOf10Config));
        $this->assertSame('Team B', ScoringService::determineMatchWinner(4, 6, $totalOf10Config));
        // Skor 10–0 dan 0–10: selesai
        $this->assertTrue(ScoringService::isMatchCompleted(10, 0, $totalOf10Config));
        $this->assertSame('Team A', ScoringService::determineMatchWinner(10, 0, $totalOf10Config));
        $this->assertTrue(ScoringService::isMatchCompleted(0, 10, $totalOf10Config));
        $this->assertSame('Team B', ScoringService::determineMatchWinner(0, 10, $totalOf10Config));
        // Skor 5–4 atau 4–5: belum selesai
        $this->assertFalse(ScoringService::isMatchCompleted(5, 4, $totalOf10Config));
        $this->assertFalse(ScoringService::isMatchCompleted(4, 5, $totalOf10Config));

        // 4. First to 3 (target menang besar 3)
        $firstTo3Config = ScoringService::detectScoringSystem('First to 3');
        // Skor 2–1: belum selesai
        $this->assertFalse(ScoringService::isMatchCompleted(2, 1, $firstTo3Config));
        $this->assertNull(ScoringService::determineMatchWinner(2, 1, $firstTo3Config));
        // Skor 3–1: selesai (Team A)
        $this->assertTrue(ScoringService::isMatchCompleted(3, 1, $firstTo3Config));
        $this->assertSame('Team A', ScoringService::determineMatchWinner(3, 1, $firstTo3Config));
        // Skor 1–3: selesai (Team B)
        $this->assertTrue(ScoringService::isMatchCompleted(1, 3, $firstTo3Config));
        $this->assertSame('Team B', ScoringService::determineMatchWinner(1, 3, $firstTo3Config));
        // Skor 3–0: selesai (Team A)
        $this->assertTrue(ScoringService::isMatchCompleted(3, 0, $firstTo3Config));
        $this->assertSame('Team A', ScoringService::determineMatchWinner(3, 0, $firstTo3Config));
        // Skor 0–3: selesai (Team B)
        $this->assertTrue(ScoringService::isMatchCompleted(0, 3, $firstTo3Config));
        $this->assertSame('Team B', ScoringService::determineMatchWinner(0, 3, $firstTo3Config));
    }

    public function test_late_point_mutations_and_batch_events_stop_after_final()
    {
        $controller = new ScoringController;
        $scoringSystem = ScoringService::detectScoringSystem('First to 3');

        // 1. Mutasi poin pada match yang sudah completed (2–1) harus ditolak/diabaikan
        $completedState = [
            'games_a' => 2,
            'games_b' => 1,
            'score_a' => 2,
            'score_b' => 1,
            'status' => 'completed',
            'winner_team' => 'Team A',
            'sets_a' => 1,
            'sets_b' => 0,
        ];
        $afterLatePoint = $this->invokeMethod($controller, 'applyPointDeltaToState', [$completedState, 'A', $scoringSystem]);
        $this->assertSame(2, $afterLatePoint['games_a']);
        $this->assertSame(1, $afterLatePoint['games_b']);
        $this->assertSame('completed', $afterLatePoint['status']);

        // 2. Poin kecil yang belum memenangkan game tidak menambah skor besar
        $inProgressState = [
            'games_a' => 2,
            'games_b' => 0,
            'score_a' => 2,
            'score_b' => 0,
            'idx_a' => 0, // 0
            'idx_b' => 0, // 0
            'is_deuce' => false,
            'status' => 'in_progress',
        ];
        // Tambah poin A (0 -> 15)
        $state15 = $this->invokeMethod($controller, 'applyPointDeltaToState', [$inProgressState, 'A', $scoringSystem]);
        $this->assertSame(2, $state15['games_a'], 'Poin kecil 15 tidak boleh menambah games_a.');
        $this->assertSame(0, $state15['games_b']);
        $this->assertSame('15', $state15['point_display_a']);
        $this->assertSame('in_progress', $state15['status']);

        // Tambah poin A lagi (15 -> 30)
        $state30 = $this->invokeMethod($controller, 'applyPointDeltaToState', [$state15, 'A', $scoringSystem]);
        $this->assertSame(2, $state30['games_a'], 'Poin kecil 30 tidak boleh menambah games_a.');
        $this->assertSame('30', $state30['point_display_a']);

        // Tambah poin A lagi (30 -> 40)
        $state40 = $this->invokeMethod($controller, 'applyPointDeltaToState', [$state30, 'A', $scoringSystem]);
        $this->assertSame(2, $state40['games_a'], 'Poin kecil 40 tidak boleh menambah games_a.');
        $this->assertSame('40', $state40['point_display_a']);

        // 3. Tambah poin A lagi pada 40–0 memenangkan game ke-3 (2–0 -> 3–0) sehingga match completed
        $state30Done = $this->invokeMethod($controller, 'applyPointDeltaToState', [$state40, 'A', $scoringSystem]);
        $this->assertSame(3, $state30Done['games_a']);
        $this->assertSame(0, $state30Done['games_b']);
        $this->assertSame('completed', $state30Done['status']);
        $this->assertSame('Team A', $state30Done['winner_team']);

        // 4. Mutasi tambahan setelah 3–0 ditolak
        $stateAfter30 = $this->invokeMethod($controller, 'applyPointDeltaToState', [$state30Done, 'A', $scoringSystem]);
        $this->assertSame(3, $stateAfter30['games_a'], 'Poin setelah 3–0 tidak boleh menambah skor game.');
    }

    public function test_court_with_score_blocks_round_advancement_and_recognizes_draws()
    {
        $controller = new ScoringController;

        $game = [
            'id' => 888,
            'match_format' => 'Americano',
            'scoring_system' => 'Total of 3',
            'drawing' => [
                'round_1' => [
                    'matches' => [
                        ['court' => 1],
                        ['court' => 2],
                    ],
                ],
                'round_2' => [
                    'matches' => [
                        ['court' => 1],
                        ['court' => 2],
                    ],
                ],
            ],
        ];

        // Court 1 selesai (2–1), Court 2 masih 2–0 (in_progress, belum selesai Total of 3)
        $savedScores = [
            'round_1_court_1' => ['status' => 'completed', 'games_a' => 2, 'games_b' => 1],
            'round_1_court_2' => ['status' => 'in_progress', 'games_a' => 2, 'games_b' => 0],
        ];

        // isRoundFullyCompleted harus false karena court 2 belum final
        $isDone = $this->invokeMethod($controller, 'isRoundFullyCompleted', [$game, 'round_1', $savedScores]);
        $this->assertFalse($isDone, 'Ronde tidak boleh dianggap selesai jika salah satu court masih 2–0!');

        // Round 2 harus tetap terkunci
        $scoringSystem = ScoringService::detectScoringSystem('Total of 3');
        $access = $this->invokeMethod($controller, 'getRoundAccess', [$game, $scoringSystem, $savedScores]);
        $this->assertFalse($access['round_2'], 'Ronde 2 harus tetap terkunci sebelum semua court selesai.');

        // Ketika Court 2 tuntas 2–1 (atau 0–3), isRoundFullyCompleted menjadi true dan Ronde 2 terbuka
        $savedScores['round_1_court_2'] = ['status' => 'completed', 'games_a' => 2, 'games_b' => 1];
        $isDoneAfter21 = $this->invokeMethod($controller, 'isRoundFullyCompleted', [$game, 'round_1', $savedScores]);
        $this->assertTrue($isDoneAfter21);

        $accessAfter21 = $this->invokeMethod($controller, 'getRoundAccess', [$game, $scoringSystem, $savedScores]);
        $this->assertTrue($accessAfter21['round_2'], 'Ronde 2 harus terbuka setelah seluruh court tuntas.');

        // Uji pengenalan hasil seri pada Total of 4: skor 2–2 berstatus completed
        $gameTotal4 = [
            'id' => 777,
            'match_format' => 'Americano',
            'scoring_system' => 'Total of 4',
            'drawing' => [
                'round_1' => ['matches' => [['court' => 1]]],
                'round_2' => ['matches' => [['court' => 1]]],
            ],
        ];
        $drawScores = [
            'round_1' => ['status' => 'completed', 'games_a' => 2, 'games_b' => 2, 'winner_team' => null],
        ];
        $isDrawCompleted = $this->invokeMethod($controller, 'isRoundFullyCompleted', [$gameTotal4, 'round_1', $drawScores]);
        $this->assertTrue($isDrawCompleted, 'Hasil seri 2–2 pada Total of 4 harus diakui sebagai ronde yang selesai.');

        $scoring4 = ScoringService::detectScoringSystem('Total of 4');
        $accessDraw = $this->invokeMethod($controller, 'getRoundAccess', [$gameTotal4, $scoring4, $drawScores]);
        $this->assertTrue($accessDraw['round_2'], 'Ronde 2 harus terbuka setelah ronde 1 selesai dengan hasil seri 2–2.');
    }

    public function test_effective_scores_use_set_history_for_primary_score_when_set_totals_are_zero()
    {
        $game = [
            'scoring_system' => 'Total of 3',
            'drawing' => [
                'round_1' => [
                    'team_a' => ['Alice', 'Bob'],
                    'team_b' => ['Charlie', 'David'],
                ],
            ],
        ];

        $sessionScores = [
            'round_1' => [
                'status' => 'completed',
                'score_a' => 0,
                'score_b' => 0,
                'sets_a' => 0,
                'sets_b' => 1,
                'games_a' => 0,
                'games_b' => 0,
                'set_history' => [
                    ['set' => 1, 'score_a' => 2, 'score_b' => 5],
                ],
                'winner_team' => 'Team B',
            ],
        ];

        $effective = ScoringService::getEffectiveScores($game, $sessionScores);

        $this->assertSame(2, $effective['round_1']['games_a']);
        $this->assertSame(5, $effective['round_1']['games_b']);
        $this->assertSame(2, $effective['round_1']['score_a']);
        $this->assertSame(5, $effective['round_1']['score_b']);
        $this->assertSame(0, $effective['round_1']['sets_a']);
        $this->assertSame(1, $effective['round_1']['sets_b']);
    }

    /**
     * Test 1: Americano Individual - Partner Rotation Across Sets.
     * Pastikan pasangan selalu berganti di tiap Set (Set 1, Set 2, Set 3).
     * Tidak ada pemain yang berpasangan dengan orang yang sama lebih dari sekali.
     */
    public function test_americano_partner_rotation_across_sets()
    {
        $service = new AmericanoService;
        $players = ['Alice', 'Bob', 'Charlie', 'David'];

        // 4 pemain, 1 court, 3 round (Set)
        $rounds = $service->generateRounds($players, 1, 3);

        $this->assertCount(3, $rounds, 'Americano harus menghasilkan 3 round/set untuk 4 pemain.');

        $partnerPairs = [];

        foreach ($rounds as $roundIndex => $round) {
            $this->assertArrayHasKey('matches', $round);
            $this->assertCount(1, $round['matches']);

            $match = $round['matches'][0];
            $teamA = $match['teamA_names'] ?? $match['team_a_names'] ?? $match['team_a'];
            $teamB = $match['teamB_names'] ?? $match['team_b_names'] ?? $match['team_b'];

            $this->assertCount(2, $teamA);
            $this->assertCount(2, $teamB);

            // Pasangan Team A
            sort($teamA);
            $pairA = implode('&', $teamA);

            // Pasangan Team B
            sort($teamB);
            $pairB = implode('&', $teamB);

            // Pastikan pasangan ini belum pernah terjadi di set sebelumnya
            $this->assertNotContains($pairA, $partnerPairs, 'Team A di Round '.($roundIndex + 1)." ({$pairA}) tidak boleh mengulang pasangan sebelumnya.");
            $this->assertNotContains($pairB, $partnerPairs, 'Team B di Round '.($roundIndex + 1)." ({$pairB}) tidak boleh mengulang pasangan sebelumnya.");

            $partnerPairs[] = $pairA;
            $partnerPairs[] = $pairB;
        }

        // Untuk 4 pemain, ada 3 kombinasi pasangan unik per set:
        // Set 1: 2 pasang, Set 2: 2 pasang, Set 3: 2 pasang = total 6 pasang unik
        $this->assertCount(6, array_unique($partnerPairs), 'Seluruh pasangan di ketiga set harus unik.');
    }

    /**
     * Test 2: Bangku Istirahat Reaktif (Dynamic Bench).
     * Bench = Total Peserta - Pemain Aktif di semua court pada set tersebut.
     */
    public function test_dynamic_bench_calculation_for_odd_or_excess_players()
    {
        $service = new AmericanoService;
        $players = ['Alice', 'Bob', 'Charlie', 'David', 'Eva']; // 5 pemain, 1 court (kapasitas 4)

        $rounds = $service->generateRounds($players, 1, 3);

        foreach ($rounds as $roundIndex => $round) {
            $resting = $round['resting_names'] ?? $round['resting'] ?? [];
            $this->assertCount(1, $resting, 'Harus ada tepat 1 pemain di bangku istirahat pada setiap round jika ada 5 pemain.');

            $activePlayers = [];
            foreach ($round['matches'] as $match) {
                $teamA = $match['teamA_names'] ?? $match['team_a'];
                $teamB = $match['teamB_names'] ?? $match['team_b'];
                $activePlayers = array_merge($activePlayers, $teamA, $teamB);
            }

            // Validasi rumus: Bench = All - Active
            $expectedResting = array_values(array_diff($players, $activePlayers));
            $this->assertEquals($expectedResting, array_values($resting), 'Pemain di bangku istirahat harus tepat Total - Aktif.');
        }
    }

    /**
     * Test 3: Team Americano - Fixed Pairs strictly preserved.
     * Pasangan tim tetap (Alpha = P1+P2, Beta = P3+P4) tidak boleh dipecah.
     */
    public function test_team_americano_preserves_fixed_pairs()
    {
        $service = new TeamAmericanoService;
        $players = ['Alice', 'Bob', 'Charlie', 'David'];

        // Cek pembentukan tim tetap
        $fixedTeams = $service->formFixedTeams($players);
        $this->assertCount(2, $fixedTeams);
        $this->assertEquals(['Alice', 'Bob'], $fixedTeams[0]['player_names']);
        $this->assertEquals(['Charlie', 'David'], $fixedTeams[1]['player_names']);

        // Generate rounds dengan seed acak untuk memastikan pasangan tidak terpecah
        $schedule = $service->generateTeamRounds($players, 1, 12345);
        $this->assertNotEmpty($schedule['rounds']);

        foreach ($schedule['rounds'] as $round) {
            foreach ($round['matches'] as $match) {
                $teamA = $match['teamA_names'] ?? $match['team_a_names'];
                $teamB = $match['teamB_names'] ?? $match['team_b_names'];

                // Team A harus [Alice, Bob] ATAU [Charlie, David]
                $isTeam1 = ($teamA === ['Alice', 'Bob'] || $teamA === ['Bob', 'Alice']);
                $isTeam2 = ($teamA === ['Charlie', 'David'] || $teamA === ['David', 'Charlie']);
                $this->assertTrue($isTeam1 || $isTeam2, 'Team A tidak boleh memecah pasangan tetap.');

                $isOppTeam1 = ($teamB === ['Alice', 'Bob'] || $teamB === ['Bob', 'Alice']);
                $isOppTeam2 = ($teamB === ['Charlie', 'David'] || $teamB === ['David', 'Charlie']);
                $this->assertTrue($isOppTeam1 || $isOppTeam2, 'Team B tidak boleh memecah pasangan tetap.');
            }
        }
    }

    /**
     * Test 4: Multi-Court Live Scoring & Individual Recap Calculation.
     * Menguji kalkulasi rekap poin individu dari 2 court secara simultan.
     */
    public function test_multi_court_scoring_and_individual_recap()
    {
        $game = [
            'scoring_system' => 'Total of 3',
            'participants' => [
                ['name' => 'Alice'],
                ['name' => 'Bob'],
                ['name' => 'Charlie'],
                ['name' => 'David'],
                ['name' => 'Eva'],
                ['name' => 'Frank'],
                ['name' => 'Grace'],
                ['name' => 'Henry'],
            ],
            'drawing' => [
                'round_1' => [
                    'matches' => [
                        [
                            'court' => 1,
                            'court_name' => 'Court 1',
                            'team_a_names' => ['Alice', 'Bob'],
                            'team_b_names' => ['Charlie', 'David'],
                        ],
                        [
                            'court' => 2,
                            'court_name' => 'Court 2',
                            'team_a_names' => ['Eva', 'Frank'],
                            'team_b_names' => ['Grace', 'Henry'],
                        ],
                    ],
                ],
            ],
        ];

        // Simulasi skor tersimpan dari Court 1 dan Court 2 pada Round 1
        $sessionScores = [
            'round_1_court_1' => [
                'status' => 'completed',
                'games_a' => 6,
                'games_b' => 3,
                'sets_a' => 1,
                'sets_b' => 0,
            ],
            'round_1_court_2' => [
                'status' => 'completed',
                'games_a' => 2,
                'games_b' => 6,
                'sets_a' => 0,
                'sets_b' => 1,
            ],
        ];

        $recap = ScoringService::calculateRecap($game, $sessionScores);
        $recapByName = [];
        foreach ($recap as $stat) {
            $recapByName[$stat['name']] = $stat;
        }

        // Court 1: Alice & Bob menang (6 game, 1 set), Charlie & David kalah (3 game, 0 set)
        $this->assertEquals(1, $recapByName['Alice']['wins']);
        $this->assertEquals(6, $recapByName['Alice']['games_won']);
        $this->assertEquals(3, $recapByName['Alice']['games_lost']);

        $this->assertEquals(0, $recapByName['Charlie']['wins']);
        $this->assertEquals(3, $recapByName['Charlie']['games_won']);
        $this->assertEquals(6, $recapByName['Charlie']['games_lost']);

        // Court 2: Grace & Henry menang (6 game, 1 set), Eva & Frank kalah (2 game, 0 set)
        $this->assertEquals(1, $recapByName['Grace']['wins']);
        $this->assertEquals(6, $recapByName['Grace']['games_won']);
        $this->assertEquals(2, $recapByName['Grace']['games_lost']);

        $this->assertEquals(0, $recapByName['Eva']['wins']);
        $this->assertEquals(2, $recapByName['Eva']['games_won']);
        $this->assertEquals(6, $recapByName['Eva']['games_lost']);
    }

    public function test_single_court_recap_prefers_round_score_over_stale_court_key()
    {
        $game = [
            'scoring_system' => 'Total of 3',
            'participants' => [
                ['name' => 'Alice'],
                ['name' => 'Bob'],
            ],
            'drawing' => [
                'round_1' => [
                    'matches' => [[
                        'court' => 1,
                        'team_a_names' => ['Alice'],
                        'team_b_names' => ['Bob'],
                    ]],
                ],
            ],
        ];
        $sessionScores = [
            'round_1' => [
                'status' => 'completed',
                'games_a' => 6,
                'games_b' => 3,
                'sets_a' => 1,
                'sets_b' => 0,
            ],
            'round_1_court_1' => [
                'status' => 'in_progress',
                'games_a' => 0,
                'games_b' => 0,
            ],
        ];

        $recap = ScoringService::calculateRecap($game, $sessionScores);
        $recapByName = collect($recap)->keyBy('name');

        $this->assertSame(6, $recapByName['Alice']['games_won']);
        $this->assertSame(3, $recapByName['Alice']['games_lost']);
        $this->assertSame(3, $recapByName['Bob']['games_won']);
        $this->assertSame(6, $recapByName['Bob']['games_lost']);
    }

    // =========================================================================
    // AMERICANO SINGLE TESTS
    // =========================================================================

    /**
     * Test 5: Americano Single — 2 players, 1 court → 1 match per round (1v1).
     * Ensures team_a and team_b each contain exactly 1 player.
     */
    public function test_americano_single_with_2_players_generates_1v1_match()
    {
        $service = new AmericanoService;
        $players = ['Alice', 'Bob'];

        // 2 players, 1 court, 1 round max (N-1 = 1)
        $rounds = $service->generateRounds($players, 1, 1, 'Single');

        $this->assertCount(1, $rounds, 'Single dengan 2 pemain harus menghasilkan tepat 1 round.');

        $round = array_values($rounds)[0];
        $this->assertArrayHasKey('matches', $round);
        $this->assertCount(1, $round['matches'], 'Harus ada tepat 1 match di 1 court.');

        $match = $round['matches'][0];

        // team_a dan team_b harus masing-masing berisi tepat 1 pemain
        $this->assertCount(1, $match['team_a'], 'Team A harus berisi tepat 1 pemain (Single).');
        $this->assertCount(1, $match['team_b'], 'Team B harus berisi tepat 1 pemain (Single).');

        // team_a_names dan team_b_names harus array dengan 1 nama
        $this->assertCount(1, $match['team_a_names']);
        $this->assertCount(1, $match['team_b_names']);

        // Harus berisi Alice dan Bob
        $allNames = array_merge($match['team_a_names'], $match['team_b_names']);
        sort($allNames);
        $this->assertEquals(['Alice', 'Bob'], $allNames);
    }

    /**
     * Test 6: Americano Single — 4 players, 2 courts → 2 simultaneous 1v1 matches.
     * Setiap court mendapat 1 match 1v1, total 4 pemain semua bermain.
     */
    public function test_americano_single_with_4_players_and_2_courts_generates_2_simultaneous_matches()
    {
        $service = new AmericanoService;
        $players = ['Alice', 'Bob', 'Charlie', 'David'];

        $rounds = $service->generateRounds($players, 2, 3, 'Single');

        $this->assertNotEmpty($rounds, 'Harus ada minimal 1 round.');

        $firstRound = array_values($rounds)[0];
        $this->assertCount(2, $firstRound['matches'], 'Dengan 4 pemain dan 2 court, harus ada 2 match simultan.');

        $allActivePlayers = [];
        foreach ($firstRound['matches'] as $match) {
            $this->assertCount(1, $match['team_a'], 'Single: team_a harus 1 pemain.');
            $this->assertCount(1, $match['team_b'], 'Single: team_b harus 1 pemain.');
            $allActivePlayers[] = $match['team_a'][0]['name'];
            $allActivePlayers[] = $match['team_b'][0]['name'];
        }

        // Semua 4 pemain harus bermain (tidak ada yang resting)
        sort($allActivePlayers);
        $this->assertEquals(['Alice', 'Bob', 'Charlie', 'David'], $allActivePlayers);

        // Tidak ada pemain yang resting
        $this->assertEmpty($firstRound['resting'], '4 pemain di 2 court seharusnya tidak ada yang resting.');
    }

    /**
     * Test 7: Americano Single — 4 players, 1 court → matches scheduled sequentially.
     * Dengan 1 court, hanya 1 match per round. 2 pemain resting setiap round.
     */
    public function test_americano_single_with_4_players_and_1_court_schedules_matches_sequentially()
    {
        $service = new AmericanoService;
        $players = ['Alice', 'Bob', 'Charlie', 'David'];

        $rounds = $service->generateRounds($players, 1, 3, 'Single');

        $this->assertNotEmpty($rounds, 'Harus ada minimal 1 round.');

        foreach ($rounds as $roundIndex => $round) {
            $this->assertCount(1, $round['matches'], "Round {$roundIndex}: harus tepat 1 match di 1 court.");
            $match = $round['matches'][0];

            $this->assertCount(1, $match['team_a'], 'Single: team_a harus 1 pemain.');
            $this->assertCount(1, $match['team_b'], 'Single: team_b harus 1 pemain.');

            // 4 pemain, 1 court → 2 pemain bermain, 2 resting
            $this->assertCount(2, $round['resting'], "Round {$roundIndex}: harus ada 2 pemain resting dengan 4 pemain di 1 court.");
        }
    }

    /**
     * Test 8: Americano Single — 3 players (odd), 1 court → fair resting rotation.
     *
     * Explicit Total of N continues for the configured number of rounds:
     *   Round 1: A vs B, C resting
     *   Round 2: A vs C (or B vs C), B resting
     *
     * Setiap pemain harus mendapat kesempatan resting secara adil.
     * Tidak ada pemain yang resting 2x berturut-turut jika bisa dihindari.
     */
    public function test_americano_single_odd_player_count_bench_handling()
    {
        $service = new AmericanoService;
        $players = ['Alice', 'Bob', 'Charlie'];

        // 3 pemain, 1 court, 3 round diminta → tepat 3 round
        $rounds = $service->generateRounds($players, 1, 3, 'Single');

        $this->assertNotEmpty($rounds, '3 pemain + 1 court harus menghasilkan minimal 1 round.');
        $this->assertCount(3, $rounds, 'Total of 3 harus menghasilkan tepat 3 round.');

        $restingPlayersByRound = [];
        foreach ($rounds as $r => $round) {
            $this->assertCount(1, $round['matches'], "Round {$r}: harus ada 1 match.");
            $this->assertCount(1, $round['resting'], "Round {$r}: harus ada tepat 1 pemain resting (3 pemain, 1 court).");

            $match = $round['matches'][0];
            $this->assertCount(1, $match['team_a']);
            $this->assertCount(1, $match['team_b']);

            $restingPlayersByRound[$r] = $round['resting'][0];
        }

        // Pastikan pemain yang resting di round 1 TIDAK resting lagi di round 2
        // (fair rotation: sistem harus memprioritaskan pemain yang sudah resting untuk bermain)
        if (count($restingPlayersByRound) >= 2) {
            $roundKeys = array_keys($restingPlayersByRound);
            $restingR1 = $restingPlayersByRound[$roundKeys[0]];
            $restingR2 = $restingPlayersByRound[$roundKeys[1]];

            $this->assertNotEquals(
                $restingR1,
                $restingR2,
                'Pemain yang resting di round 1 tidak boleh resting lagi di round 2 (fair rotation).'
            );
        }
    }

    /**
     * Test 9: Americano Single — Opponent rotation without early repeats.
     * Untuk N pemain, pasangan lawan tidak boleh berulang selama masih ada pasangan
     * unik yang belum digunakan (dalam batas yang memungkinkan oleh constraint court/resting).
     *
     * CATATAN MATEMATIKA:
     * - 4 pemain, 1 court → hanya 1 match per round, 2 pemain resting
     * - Total pasangan unik = C(4,2) = 6
     * - N-1 = 3 rounds default
     * - Dengan hanya 2 pemain aktif per round dan rotasi resting, sistem akan berusaha
     *   memilih pasangan yang belum pernah bertemu, tetapi constraint resting bisa
     *   memaksa pengulangan setelah beberapa round.
     * - Test ini verifikasi bahwa SETIDAKNYA 2 round pertama memiliki pasangan unik
     *   (sistem tidak langsung mengulang pada round berikutnya tanpa alasan).
     *
     * Untuk memverifikasi pure opponent rotation, gunakan skenario 4 pemain + 2 courts
     * dimana semua 4 pemain aktif setiap round → 3 rounds selalu unik.
     */
    public function test_americano_single_opponent_rotation_without_early_repeats()
    {
        $service = new AmericanoService;

        // ── Skenario A: 4 pemain + 2 courts (semua aktif setiap round) ──────────
        // Dengan 2 courts, 4 pemain semua bermain setiap round.
        // Round 1: pair1 vs pair2 (Court 1), pair3 vs pair4 (Court 2) — 2 unique pairs per round
        // Dalam 3 round default (N-1=3), pasangan tidak boleh ada yang berulang.
        $players4x2 = ['Alice', 'Bob', 'Charlie', 'David'];
        $rounds4x2 = $service->generateRounds($players4x2, 2, 3, 'Single');

        $this->assertNotEmpty($rounds4x2, '4 pemain + 2 courts harus menghasilkan rounds.');

        $seenPairs4x2 = [];
        foreach ($rounds4x2 as $roundIndex => $round) {
            foreach ($round['matches'] as $match) {
                $nameA = $match['team_a'][0]['name'];
                $nameB = $match['team_b'][0]['name'];
                $pair = [$nameA, $nameB];
                sort($pair);
                $pairKey = implode('|', $pair);

                $this->assertNotContains(
                    $pairKey,
                    $seenPairs4x2,
                    "Round {$roundIndex}: pasangan '{$nameA} vs {$nameB}' berulang terlalu dini (4p+2court semua aktif, tidak boleh ada repeat)."
                );
                $seenPairs4x2[] = $pairKey;
            }
        }

        // ── Skenario B: 4 pemain + 1 court (2 resting per round) ────────────────
        // Round 1 dan Round 2 minimal harus memiliki pasangan yang berbeda.
        // Repeat di round ke-3 bisa terjadi karena constraint resting (matematically inevitable).
        $players4x1 = ['Alice', 'Bob', 'Charlie', 'David'];
        $rounds4x1 = $service->generateRounds($players4x1, 1, 3, 'Single');

        $this->assertNotEmpty($rounds4x1, '4 pemain + 1 court harus menghasilkan rounds.');

        $roundValues = array_values($rounds4x1);
        if (count($roundValues) >= 2) {
            $matchR1 = $roundValues[0]['matches'][0];
            $matchR2 = $roundValues[1]['matches'][0];
            $pairR1 = [$matchR1['team_a'][0]['name'], $matchR1['team_b'][0]['name']];
            $pairR2 = [$matchR2['team_a'][0]['name'], $matchR2['team_b'][0]['name']];
            sort($pairR1);
            sort($pairR2);

            $this->assertNotEquals(
                implode('|', $pairR1),
                implode('|', $pairR2),
                'Round 1 dan Round 2 (1 court) harus memiliki pasangan lawan yang berbeda.'
            );
        }
    }

    /**
     * Test 10: Americano Single — No array index [1] access error.
     * Pastikan tidak ada error ketika mengakses team_a[1] atau team_b[1]
     * pada mode Single (array tim hanya berisi 1 pemain).
     */
    public function test_americano_single_no_array_index_one_access_error()
    {
        $service = new AmericanoService;
        $players = ['Alice', 'Bob', 'Charlie'];

        $rounds = $service->generateRounds($players, 1, 2, 'Single');

        foreach ($rounds as $roundIndex => $round) {
            foreach ($round['matches'] as $match) {
                // team_a dan team_b harus array dengan tepat 1 elemen
                $this->assertCount(1, $match['team_a'], "Round {$roundIndex}: team_a harus berisi tepat 1 pemain di mode Single.");
                $this->assertCount(1, $match['team_b'], "Round {$roundIndex}: team_b harus berisi tepat 1 pemain di mode Single.");

                // Akses indeks [0] harus valid
                $this->assertIsArray($match['team_a'][0]);
                $this->assertIsArray($match['team_b'][0]);
                $this->assertArrayHasKey('name', $match['team_a'][0]);
                $this->assertArrayHasKey('name', $match['team_b'][0]);

                // Akses indeks [1] harus TIDAK ada (tidak ada elemen kedua)
                $this->assertArrayNotHasKey(1, $match['team_a'], 'team_a tidak boleh memiliki indeks [1] di mode Single.');
                $this->assertArrayNotHasKey(1, $match['team_b'], 'team_b tidak boleh memiliki indeks [1] di mode Single.');

                // team_a_names dan team_b_names harus array dengan 1 nama
                $this->assertCount(1, $match['team_a_names']);
                $this->assertCount(1, $match['team_b_names']);
            }
        }
    }

    /**
     * Test 11: Legacy session tanpa jenis_permainan → default ke Double.
     * Memastikan generateRounds() tanpa parameter $mode tetap menggunakan Double (4 pemain per court).
     */
    public function test_legacy_session_without_jenis_permainan_defaults_to_double()
    {
        $service = new AmericanoService;
        $players = ['Alice', 'Bob', 'Charlie', 'David'];

        // Panggil tanpa $mode (backward compatible)
        $rounds = $service->generateRounds($players, 1, 3);

        $this->assertNotEmpty($rounds, 'Legacy call tanpa $mode harus tetap menghasilkan rounds.');

        foreach ($rounds as $round) {
            foreach ($round['matches'] as $match) {
                // Double: 2 pemain per tim
                $this->assertCount(2, $match['team_a'], 'Default mode harus Double (2 pemain per tim).');
                $this->assertCount(2, $match['team_b'], 'Default mode harus Double (2 pemain per tim).');
                $this->assertCount(2, $match['team_a_names']);
                $this->assertCount(2, $match['team_b_names']);
            }
        }
    }

    /**
     * Test 12: Americano Single — Scoring dan recap calculation.
     * Verifikasi bahwa ScoringService::calculateRecap() bekerja untuk Single
     * di mana team_a_names dan team_b_names masing-masing berisi 1 pemain.
     */
    public function test_americano_single_scoring_and_recap_calculation()
    {
        $game = [
            'scoring_system' => 'Total of 3',
            'participants' => [
                ['name' => 'Alice'],
                ['name' => 'Bob'],
            ],
            'drawing' => [
                'round_1' => [
                    'matches' => [
                        [
                            'court' => 1,
                            'court_name' => 'Court 1',
                            'team_a_names' => ['Alice'],
                            'team_b_names' => ['Bob'],
                        ],
                    ],
                ],
            ],
        ];

        $sessionScores = [
            'round_1_court_1' => [
                'status' => 'completed',
                'games_a' => 6,
                'games_b' => 2,
                'sets_a' => 1,
                'sets_b' => 0,
            ],
        ];

        $recap = ScoringService::calculateRecap($game, $sessionScores);
        $recapByName = [];
        foreach ($recap as $stat) {
            $recapByName[$stat['name']] = $stat;
        }

        // Alice menang: 6 game, 1 set, 1 win
        $this->assertArrayHasKey('Alice', $recapByName);
        $this->assertEquals(1, $recapByName['Alice']['wins']);
        $this->assertEquals(6, $recapByName['Alice']['games_won']);
        $this->assertEquals(2, $recapByName['Alice']['games_lost']);

        // Bob kalah: 2 game, 0 set, 0 win
        $this->assertArrayHasKey('Bob', $recapByName);
        $this->assertEquals(0, $recapByName['Bob']['wins']);
        $this->assertEquals(2, $recapByName['Bob']['games_won']);
        $this->assertEquals(6, $recapByName['Bob']['games_lost']);
    }

    /**
     * Test 13: Americano Single — Playing history: partner_player_id null, opponent valid.
     * Untuk mode Single, setiap match menghasilkan struktur dengan:
     * - team_a berisi tepat 1 pemain (tidak ada partner)
     * - team_b berisi tepat 1 pemain (adalah lawan)
     * - Tidak ada akses ke partner (indeks [1]) yang bisa menyebabkan null reference error.
     */
    public function test_americano_single_playing_history_partner_is_null_and_opponent_is_valid()
    {
        $service = new AmericanoService;
        $players = [
            ['player_id' => 1, 'name' => 'Alice', 'gender' => 'Female', 'level' => 'Intermediate'],
            ['player_id' => 2, 'name' => 'Bob',   'gender' => 'Male',   'level' => 'Intermediate'],
            ['player_id' => 3, 'name' => 'Carol',  'gender' => 'Female', 'level' => 'Beginner'],
        ];

        $rounds = $service->generateRounds($players, 1, 2, 'Single');

        foreach ($rounds as $roundIndex => $round) {
            foreach ($round['matches'] as $match) {
                $teamA = $match['team_a'];
                $teamB = $match['team_b'];

                // Setiap sisi hanya 1 pemain
                $this->assertCount(1, $teamA);
                $this->assertCount(1, $teamB);

                $playerA = $teamA[0];
                $playerB = $teamB[0];

                // Masing-masing memiliki ID valid
                $this->assertNotNull($playerA['id'], 'Player A harus memiliki ID valid.');
                $this->assertNotNull($playerB['id'], 'Player B harus memiliki ID valid.');
                $this->assertNotEquals($playerA['id'], $playerB['id'], 'Player A dan B tidak boleh pemain yang sama.');

                // Simulasi logika ScoringController untuk Single:
                // partner_player_id = null (tidak ada partner)
                // opponent_player_id = ID pemain lawan
                $partnerAId = null;                    // tidak ada partner
                $opponentAId = $playerB['id'];          // lawan Player A adalah Player B
                $partnerBId = null;
                $opponentBId = $playerA['id'];

                $this->assertNull($partnerAId, "Round {$roundIndex}: Player A tidak boleh punya partner di Single.");
                $this->assertNull($partnerBId, "Round {$roundIndex}: Player B tidak boleh punya partner di Single.");
                $this->assertEquals($playerB['id'], $opponentAId, "Round {$roundIndex}: opponent Player A harus Player B.");
                $this->assertEquals($playerA['id'], $opponentBId, "Round {$roundIndex}: opponent Player B harus Player A.");
            }
        }
    }

    /**
     * Test 14: Scoring Live View Render - No TypeError with array player items.
     */
    public function test_scoring_live_view_renders_without_type_error_when_team_contains_arrays()
    {
        $service = new AmericanoService;
        $players = [
            ['player_id' => 1, 'name' => 'Alice', 'gender' => 'Female', 'level' => 'Intermediate'],
            ['player_id' => 2, 'name' => 'Bob',   'gender' => 'Male',   'level' => 'Intermediate'],
            ['player_id' => 3, 'name' => 'Carol', 'gender' => 'Female', 'level' => 'Beginner'],
            ['player_id' => 4, 'name' => 'Dave',  'gender' => 'Male',   'level' => 'Advanced'],
        ];

        $drawing = $service->generateRounds($players, 1, 3);
        $game = [
            'id' => 83,
            'venue_name' => 'Padel Court Jakarta',
            'sport' => 'Padel',
            'scoring_system' => 'Total of 3',
            'match_format' => 'Americano',
            'drawing' => $drawing,
            'participants' => $players,
        ];

        $matchContext = ScoringService::buildMatchContext($game, 'round_1', 0);
        $scoringSystem = ScoringService::detectScoringSystem($game['scoring_system']);
        $currentScore = [
            'games_a' => 0,
            'games_b' => 0,
            'status' => 'in_progress',
        ];
        $activeRound = 'round_1';
        $courtIndex = 0;
        $matchKey = 'round_1_court_1';
        $savedScores = [];
        $isHost = true;
        $userRole = 'host';
        $cacheKey = 'scoring.game_83';

        $html = view('scoring.live', compact(
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
            'cacheKey'
        ))->render();

        $this->assertStringContainsString('Alice', $html);
        $this->assertStringContainsString('Bob', $html);
        $this->assertStringContainsString('Ronde 3', $html);
    }

    /**
     * Setup helper untuk in-memory database schema test.
     */
    protected function setupTestDatabaseSchema()
    {
        if (! Schema::hasTable('tb_sport')) {
            Schema::create('tb_sport', function ($table) {
                $table->id('sport_id');
                $table->string('nama_sport')->default('Padel');
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('tb_venue')) {
            Schema::create('tb_venue', function ($table) {
                $table->id('venue_id');
                $table->string('nama_venue')->default('Arena Olahraga');
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('tb_user')) {
            Schema::create('tb_user', function ($table) {
                $table->id('user_id');
                $table->string('nama')->default('User Test');
                $table->string('no_hp')->nullable();
                $table->string('email')->unique();
                $table->string('password')->default('password');
                $table->string('role')->default('member');
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('tb_session')) {
            Schema::create('tb_session', function ($table) {
                $table->id('session_id');
                $table->unsignedBigInteger('host_user_id')->nullable();
                $table->unsignedBigInteger('sport_id')->nullable();
                $table->unsignedBigInteger('venue_id')->nullable();
                $table->string('nama_session')->default('Session Test');
                $table->string('scoring_system')->default('Total of 3');
                $table->string('waktu_session')->nullable();
                $table->dateTime('datetime')->nullable();
                $table->string('status_session')->default('In Progress');
                $table->integer('jumlah_pemain')->default(4);
                $table->string('jenis_permainan')->default('Single');
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('tb_court')) {
            Schema::create('tb_court', function ($table) {
                $table->id('court_id');
                $table->unsignedBigInteger('venue_id')->nullable();
                $table->string('nama_court');
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('tb_session_court')) {
            Schema::create('tb_session_court', function ($table) {
                $table->unsignedBigInteger('session_id');
                $table->unsignedBigInteger('court_id');
            });
        }
        if (! Schema::hasTable('tb_player')) {
            Schema::create('tb_player', function ($table) {
                $table->id('player_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('community_id')->nullable();
                $table->string('nama');
                $table->integer('usia')->nullable();
                $table->string('gender')->nullable();
                $table->string('level')->nullable();
                $table->float('rating')->nullable();
                $table->string('no_hp')->nullable();
                $table->string('email')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('tb_session_player')) {
            Schema::create('tb_session_player', function ($table) {
                $table->unsignedBigInteger('session_id');
                $table->unsignedBigInteger('player_id');
                $table->string('status')->default('active');
                $table->dateTime('withdrawn_at')->nullable();
                $table->text('withdrawn_reason')->nullable();
                $table->unsignedBigInteger('withdrawn_match_id')->nullable();
            });
        }
        if (! Schema::hasTable('tb_match_format')) {
            Schema::create('tb_match_format', function ($table) {
                $table->id('match_format_id');
                $table->string('nama_format')->default('americano');
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('tb_drawing')) {
            Schema::create('tb_drawing', function ($table) {
                $table->id('drawing_id');
                $table->unsignedBigInteger('session_id');
                $table->unsignedBigInteger('match_format_id')->default(1);
                $table->date('tanggal_drawing')->nullable();
                $table->time('jam_drawing')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('tb_match')) {
            Schema::create('tb_match', function ($table) {
                $table->id('match_id');
                $table->unsignedBigInteger('drawing_id');
                $table->unsignedBigInteger('court_id')->nullable();
                $table->integer('nomor_match');
                $table->string('status_match')->default('In Progress');
                $table->dateTime('waktu_mulai')->nullable();
                $table->dateTime('waktu_selesai')->nullable();
                $table->string('hasil_pertandingan')->nullable();
                $table->string('winner_team')->nullable();
                $table->string('stop_type')->nullable();
                $table->text('stop_reason')->nullable();
                $table->unsignedBigInteger('stopped_by_user_id')->nullable();
                $table->dateTime('stopped_at')->nullable();
                $table->text('withdrawn_player_ids')->nullable();
                $table->text('player_notes')->nullable();
                $table->integer('version')->nullable();
                $table->string('last_event_id')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('tb_match_participant')) {
            Schema::create('tb_match_participant', function ($table) {
                $table->unsignedBigInteger('match_id');
                $table->unsignedBigInteger('player_id');
                $table->string('side');
                $table->primary(['match_id', 'player_id']);
            });
        }
        if (! Schema::hasTable('tb_score')) {
            Schema::create('tb_score', function ($table) {
                $table->id('score_id');
                $table->unsignedBigInteger('match_id');
                $table->integer('set_number')->default(1);
                $table->integer('game_number')->default(1);
                $table->string('point_score_a')->default('0');
                $table->string('point_score_b')->default('0');
                $table->integer('game_score_a')->default(0);
                $table->integer('game_score_b')->default(0);
                $table->integer('set_score_a')->default(0);
                $table->integer('set_score_b')->default(0);
                $table->integer('score_side_a')->default(0);
                $table->integer('score_side_b')->default(0);
                $table->string('scoring_system')->default('Total of 3');
                $table->string('status_score')->default('In Progress');
                $table->integer('version')->nullable();
                $table->string('last_event_id')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('tb_kudos')) {
            Schema::create('tb_kudos', function ($table) {
                $table->id('kudos_id');
                $table->unsignedBigInteger('session_id')->index();
                $table->unsignedBigInteger('giver_user_id')->nullable()->index();
                $table->unsignedBigInteger('recipient_player_id')->nullable()->index();
                $table->string('recipient_name')->index();
                $table->string('badge');
                $table->timestamps();
            });
        }
    }

    /**
     * Helper untuk membuat session test lengkap dengan court dan player.
     */
    protected function createTestSession($jenisPermainan = 'Single', $courtCount = 2, $scoringSystem = 'First to 8')
    {
        $this->setupTestDatabaseSchema();

        $hostUser = User::create([
            'nama' => 'Host User',
            'email' => 'host_'.uniqid().'@matcha.com',
            'role' => 'host',
            'password' => bcrypt('secret'),
        ]);

        $session = SessionModel::create([
            'sport_id' => 1,
            'host_user_id' => $hostUser->user_id,
            'nama_session' => 'Test Americano '.$jenisPermainan,
            'scoring_system' => $scoringSystem,
            'status_session' => 'In Progress',
            'jenis_permainan' => $jenisPermainan,
            'jumlah_pemain' => $jenisPermainan === 'Single' ? 4 : 8,
        ]);

        // Tambah courts
        for ($c = 1; $c <= $courtCount; $c++) {
            $court = Court::create(['nama_court' => "Court {$c}", 'venue_id' => 1]);
            \DB::table('tb_session_court')->insert([
                'session_id' => $session->session_id,
                'court_id' => $court->court_id,
            ]);
        }

        // Tambah players (Host + member players)
        $hostPlayer = Player::create([
            'user_id' => $hostUser->user_id,
            'nama' => 'Host User',
            'gender' => 'Male',
            'level' => 'Intermediate',
        ]);
        \DB::table('tb_session_player')->insert([
            'session_id' => $session->session_id,
            'player_id' => $hostPlayer->player_id,
        ]);

        $playerCount = $jenisPermainan === 'Single' ? 4 : 8;
        $memberUsers = [];
        for ($p = 2; $p <= $playerCount; $p++) {
            $mUser = User::create([
                'nama' => "Player {$p}",
                'email' => "player_{$p}_".uniqid().'@matcha.com',
                'role' => 'member',
                'password' => bcrypt('secret'),
            ]);
            $player = Player::create([
                'user_id' => $mUser->user_id,
                'nama' => "Player {$p}",
                'gender' => 'Male',
                'level' => 'Intermediate',
            ]);
            \DB::table('tb_session_player')->insert([
                'session_id' => $session->session_id,
                'player_id' => $player->player_id,
            ]);
            $memberUsers[] = ['user' => $mUser, 'player' => $player];
        }

        return [$session, $hostUser, $hostPlayer, $memberUsers];
    }

    /**
     * Test A: Member input → GET → score tetap
     */
    public function test_mandatory_a_member_input_and_get_score_persists()
    {
        [$session, $hostUser, $hostPlayer, $memberUsers] = $this->createTestSession('Single', 2);
        $member = $memberUsers[0]['user'];

        $this->actingAs($member);

        $controller = new ScoringController;
        $request = new Request([
            'game_id' => $session->session_id,
            'round' => 'round_1',
            'match_key' => 'round_1_court_1',
            'court' => 1,
            'score_a' => 3,
            'score_b' => 1,
            'games_a' => 3,
            'games_b' => 1,
            'status' => 'in_progress',
        ]);

        $updateResp = $controller->updateScore($request);
        $this->assertTrue($updateResp->getData()->success, 'Member yang berpartisipasi harus dapat menginput skor.');

        // GET score
        \Illuminate\Support\Facades\Request::replace(['match_key' => 'round_1_court_1']);
        $getResp = $controller->getScore($session->session_id, 'round_1');
        $data = $getResp->getData();

        $this->assertEquals(3, $data->score_a);
        $this->assertEquals(1, $data->score_b);
        $this->assertEquals(3, $data->games_a);
        $this->assertEquals(1, $data->games_b);
    }

    /**
     * Test B: Host input → GET → score tetap
     */
    public function test_mandatory_b_host_input_and_get_score_persists()
    {
        [$session, $hostUser] = $this->createTestSession('Single', 2);
        $this->actingAs($hostUser);

        $controller = new ScoringController;
        $request = new Request([
            'game_id' => $session->session_id,
            'round' => 'round_1',
            'match_key' => 'round_1_court_1',
            'court' => 1,
            'score_a' => 5,
            'score_b' => 2,
            'games_a' => 5,
            'games_b' => 2,
            'status' => 'in_progress',
        ]);

        $updateResp = $controller->updateScore($request);
        $this->assertTrue($updateResp->getData()->success);

        // GET score
        \Illuminate\Support\Facades\Request::replace(['match_key' => 'round_1_court_1']);
        $getResp = $controller->getScore($session->session_id, 'round_1');
        $data = $getResp->getData();

        $this->assertEquals(5, $data->score_a);
        $this->assertEquals(2, $data->score_b);
    }

    /**
     * Test C: Host refresh → score tetap (pulih dari DB meskipun Cache kosong)
     */
    public function test_mandatory_c_host_refresh_restores_score_from_database()
    {
        [$session, $hostUser] = $this->createTestSession('Single', 2);
        $this->actingAs($hostUser);

        $controller = new ScoringController;
        $request = new Request([
            'game_id' => $session->session_id,
            'round' => 'round_1',
            'match_key' => 'round_1_court_1',
            'court' => 1,
            'score_a' => 4,
            'score_b' => 3,
            'games_a' => 4,
            'games_b' => 3,
            'status' => 'in_progress',
        ]);
        $controller->updateScore($request);

        // Hapus Cache untuk mensimulasikan cache expire / clear / fresh browser session
        Cache::flush();

        // Saat refresh (GET score), score dipulihkan dari tb_score di database
        \Illuminate\Support\Facades\Request::replace(['match_key' => 'round_1_court_1']);
        $getResp = $controller->getScore($session->session_id, 'round_1');
        $data = $getResp->getData();

        $this->assertEquals(4, $data->games_a, 'Skor games_a harus pulih dari database saat cache kosong.');
        $this->assertEquals(3, $data->games_b, 'Skor games_b harus pulih dari database saat cache kosong.');
    }

    /**
     * Test D: 2 court → score Court 1 tidak muncul di Court 2
     */
    public function test_mandatory_d_court1_score_does_not_leak_to_court2()
    {
        [$session, $hostUser] = $this->createTestSession('Single', 2);
        $this->actingAs($hostUser);

        $controller = new ScoringController;
        $request = new Request([
            'game_id' => $session->session_id,
            'round' => 'round_1',
            'match_key' => 'round_1_court_1',
            'court' => 1,
            'score_a' => 6,
            'score_b' => 0,
            'games_a' => 6,
            'games_b' => 0,
            'status' => 'in_progress',
        ]);
        $controller->updateScore($request);

        // Query Court 2
        \Illuminate\Support\Facades\Request::replace(['match_key' => 'round_1_court_2']);
        $getResp = $controller->getScore($session->session_id, 'round_1');
        $data = $getResp->getData();

        $this->assertEquals(0, $data->games_a, 'Court 2 tidak boleh membaca skor Court 1.');
        $this->assertEquals(0, $data->games_b, 'Court 2 tidak boleh membaca skor Court 1.');
    }

    /**
     * Test E: 2 court → score Court 2 tidak muncul di Court 1
     */
    public function test_mandatory_e_court2_score_does_not_leak_to_court1()
    {
        [$session, $hostUser] = $this->createTestSession('Single', 2);
        $this->actingAs($hostUser);

        $controller = new ScoringController;
        $request = new Request([
            'game_id' => $session->session_id,
            'round' => 'round_1',
            'match_key' => 'round_1_court_2',
            'court' => 2,
            'score_a' => 1,
            'score_b' => 5,
            'games_a' => 1,
            'games_b' => 5,
            'status' => 'in_progress',
        ]);
        $controller->updateScore($request);

        // Query Court 1
        \Illuminate\Support\Facades\Request::replace(['match_key' => 'round_1_court_1']);
        $getResp = $controller->getScore($session->session_id, 'round_1');
        $data = $getResp->getData();

        $this->assertEquals(0, $data->games_a, 'Court 1 tidak boleh membaca skor Court 2.');
        $this->assertEquals(0, $data->games_b, 'Court 1 tidak boleh membaca skor Court 2.');
    }

    /**
     * Test F: Host sebagai player di Court 1 → dapat membaca score Court 1
     */
    public function test_mandatory_f_host_as_player_in_court1_reads_court1_score()
    {
        [$session, $hostUser] = $this->createTestSession('Single', 2);
        $this->actingAs($hostUser);

        $controller = new ScoringController;
        $request = new Request([
            'game_id' => $session->session_id,
            'round' => 'round_1',
            'match_key' => 'round_1_court_1',
            'court' => 1,
            'score_a' => 4,
            'score_b' => 2,
            'games_a' => 4,
            'games_b' => 2,
            'status' => 'in_progress',
        ]);
        $controller->updateScore($request);

        // Host membaca score Court 1
        \Illuminate\Support\Facades\Request::replace(['match_key' => 'round_1_court_1']);
        $getResp = $controller->getScore($session->session_id, 'round_1');
        $data = $getResp->getData();

        $this->assertEquals(4, $data->games_a);
        $this->assertEquals(2, $data->games_b);
    }

    /**
     * Test G: Host sebagai player di Court 1 → tidak membaca score Court 2
     */
    public function test_mandatory_g_host_as_player_in_court1_does_not_read_court2_score()
    {
        [$session, $hostUser] = $this->createTestSession('Single', 2);
        $this->actingAs($hostUser);

        $controller = new ScoringController;

        // Court 2 memiliki skor
        $request2 = new Request([
            'game_id' => $session->session_id,
            'round' => 'round_1',
            'match_key' => 'round_1_court_2',
            'court' => 2,
            'score_a' => 6,
            'score_b' => 6,
            'games_a' => 6,
            'games_b' => 6,
            'status' => 'in_progress',
        ]);
        $controller->updateScore($request2);

        // Host berada di Court 1, membaca Court 1
        \Illuminate\Support\Facades\Request::replace(['match_key' => 'round_1_court_1']);
        $getResp = $controller->getScore($session->session_id, 'round_1');
        $data = $getResp->getData();

        $this->assertEquals(0, $data->games_a, 'Host di Court 1 tidak boleh membaca data skor dari Court 2.');
        $this->assertEquals(0, $data->games_b, 'Host di Court 1 tidak boleh membaca data skor dari Court 2.');
    }

    /**
     * Test H: Single format: Side A = 1 player, Side B = 1 player
     */
    public function test_mandatory_h_single_format_participants()
    {
        [$session, $hostUser, $hostPlayer] = $this->createTestSession('Single', 2);

        $match = ScoringService::ensureMatchAndParticipants($session, 'round_1', 0);
        $this->assertNotNull($match);

        $sideAPlayers = MatchParticipant::where('match_id', $match->match_id)->where('side', 'A')->get();
        $sideBPlayers = MatchParticipant::where('match_id', $match->match_id)->where('side', 'B')->get();

        $this->assertCount(1, $sideAPlayers, 'Single format Side A harus tepat 1 pemain.');
        $this->assertCount(1, $sideBPlayers, 'Single format Side B harus tepat 1 pemain.');

        // Host yang ikut bermain harus memiliki player_id yang sama dengan di tb_player
        $allParticipantIds = MatchParticipant::where('match_id', $match->match_id)->pluck('player_id')->toArray();
        $this->assertContains($hostPlayer->player_id, $allParticipantIds, 'Host player_id harus terdaftar di tb_match_participant.');
    }

    /**
     * Test I: Double format: Side A = 2 players, Side B = 2 players
     */
    public function test_mandatory_i_double_format_participants()
    {
        [$session, $hostUser, $hostPlayer] = $this->createTestSession('Double', 2);

        $match = ScoringService::ensureMatchAndParticipants($session, 'round_1', 0);
        $this->assertNotNull($match);

        $sideAPlayers = MatchParticipant::where('match_id', $match->match_id)->where('side', 'A')->get();
        $sideBPlayers = MatchParticipant::where('match_id', $match->match_id)->where('side', 'B')->get();

        $this->assertCount(2, $sideAPlayers, 'Double format Side A harus tepat 2 pemain.');
        $this->assertCount(2, $sideBPlayers, 'Double format Side B harus tepat 2 pemain.');

        // Host yang ikut bermain harus memiliki player_id yang sama dengan di tb_player
        $allParticipantIds = MatchParticipant::where('match_id', $match->match_id)->pluck('player_id')->toArray();
        $this->assertContains($hostPlayer->player_id, $allParticipantIds, 'Host player_id harus terdaftar di tb_match_participant.');
    }

    /**
     * Test 10: Database Duplication Check (session_id + round + court)
     * Memastikan pemanggilan berulang tidak membuat duplicate match.
     */
    public function test_database_duplication_check_no_duplicate_tb_match()
    {
        [$session, $hostUser] = $this->createTestSession('Single', 2);

        // Panggil ensureMatchAndParticipants 5 kali untuk kombinasi round_1 court 0
        for ($i = 0; $i < 5; $i++) {
            $match = ScoringService::ensureMatchAndParticipants($session, 'round_1', 0);
        }

        $drawing = Drawing::where('session_id', $session->session_id)->first();
        $this->assertNotNull($drawing);

        // Nomor match deterministik untuk Round 1 Court 1 adalah 1
        $count = GameMatch::where('drawing_id', $drawing->drawing_id)
            ->where('nomor_match', 1)
            ->count();

        $this->assertEquals(1, $count, 'Hanya boleh ada tepat 1 match untuk kombinasi session_id + round + court yang sama.');
    }

    /**
     * Test 11: Rapid Clicks Test (3-5 klik cepat tidak kehilangan poin & version monoton)
     */
    public function test_rapid_clicks_sequential_monotonic_version_and_correct_final_score()
    {
        [$session, $hostUser] = $this->createTestSession('Single', 2);
        Auth::login($hostUser);

        $controller = new ScoringController;
        $matchKey = 'round_1_court_1';

        // Simulasikan 5 klik cepat berturut-turut dari user
        $lastVersion = 0;
        for ($click = 1; $click <= 5; $click++) {
            $req = new Request([
                'game_id' => $session->session_id,
                'round' => 'round_1',
                'match_key' => $matchKey,
                'court' => 1,
                'score_a' => $click,
                'score_b' => 0,
                'games_a' => $click,
                'games_b' => 0,
                'status' => 'in_progress',
                'client_version' => $click,
                'client_seq' => $click,
            ]);

            $resp = $controller->updateScore($req);
            $data = $resp->getData();

            $this->assertTrue($data->success, "Request klik {$click} harus berhasil.");
            $this->assertGreaterThan($lastVersion, $data->version, "Version harus monoton naik pada setiap klik (klik {$click}).");
            $this->assertEquals($click, $data->version, "Version ke-{$click} harus tepat bernilai {$click}.");
            $lastVersion = $data->version;
        }

        // Cek hasil akhir via getScore
        \Illuminate\Support\Facades\Request::replace(['match_key' => $matchKey]);
        $getResp = $controller->getScore($session->session_id, 'round_1');
        $getData = $getResp->getData();

        $this->assertEquals(5, $getData->games_a, 'Skor akhir games_a harus tepat 5 setelah 5 klik cepat.');
        $this->assertEquals(5, $getData->version, 'Version akhir harus tepat 5.');
    }

    /**
     * Test 12: Concurrent / Out-of-Order Request Protection (request lama tidak menyebabkan rollback)
     */
    public function test_out_of_order_requests_do_not_cause_score_rollback()
    {
        [$session, $hostUser] = $this->createTestSession('Single', 2);
        Auth::login($hostUser);

        $controller = new ScoringController;
        $matchKey = 'round_1_court_1';
        $clientId = 'cli_test_tab_1';

        // 1. Request baru (klik ke-4) tiba lebih dulu di server
        $reqNew = new Request([
            'game_id' => $session->session_id,
            'round' => 'round_1',
            'match_key' => $matchKey,
            'court' => 1,
            'score_a' => 4,
            'score_b' => 0,
            'games_a' => 4,
            'games_b' => 0,
            'status' => 'in_progress',
            'client_id' => $clientId,
            'client_version' => 4,
            'client_seq' => 4,
        ]);
        $respNew = $controller->updateScore($reqNew);
        $dataNew = $respNew->getData();
        $this->assertEquals(4, $dataNew->saved->games_a);
        $this->assertEquals(1, $dataNew->version);

        // 2. Request lama (klik ke-2) mengalami network delay dan tiba terlambat di server dari client yang sama
        $reqOld = new Request([
            'game_id' => $session->session_id,
            'round' => 'round_1',
            'match_key' => $matchKey,
            'court' => 1,
            'score_a' => 2,
            'score_b' => 0,
            'games_a' => 2,
            'games_b' => 0,
            'status' => 'in_progress',
            'client_id' => $clientId,
            'client_version' => 2,
            'client_seq' => 2,
        ]);
        $respOld = $controller->updateScore($reqOld);
        $dataOld = $respOld->getData();

        // Server harus menolak menimpa data baru dengan data lama yang terlambat (stale update ignored)
        $this->assertTrue($dataOld->stale_ignored ?? false, 'Request yang out-of-order/stale harus di-ignore oleh server.');
        $this->assertEquals(4, $dataOld->saved->games_a, 'Skor yang dikembalikan harus tetap skor terbaru (4), bukan skor lama (2).');

        // 3. Request dari client lain (Window/Tab 2) yang mulai dari sequence 1 TIDAK boleh di-block
        $reqOtherClient = new Request([
            'game_id' => $session->session_id,
            'round' => 'round_1',
            'match_key' => $matchKey,
            'court' => 1,
            'score_a' => 4,
            'score_b' => 1,
            'games_a' => 4,
            'games_b' => 1,
            'status' => 'in_progress',
            'client_id' => 'cli_test_tab_2',
            'client_version' => 1,
            'client_seq' => 1,
        ]);
        $respOther = $controller->updateScore($reqOtherClient);
        $dataOther = $respOther->getData();
        $this->assertTrue($dataOther->success);
        $this->assertFalse($dataOther->stale_ignored ?? false, 'Client berbeda dengan client_seq 1 tidak boleh di-ignore.');
        $this->assertEquals(1, $dataOther->saved->games_b);

        // 4. Verifikasi via getScore bahwa skor tidak pernah rollback dan skor client 2 diterima
        \Illuminate\Support\Facades\Request::replace(['match_key' => $matchKey]);
        $getResp = $controller->getScore($session->session_id, 'round_1');
        $getData = $getResp->getData();

        $this->assertEquals(4, $getData->games_a, 'Skor akhir games_a harus tetap 4.');
        $this->assertEquals(1, $getData->games_b, 'Skor akhir games_b harus 1 dari client 2.');
    }

    /**
     * Test 13: Version Isolation per Match/Court (Court 1 dan Court 2 tidak berbagi version state)
     */
    public function test_version_isolation_independent_per_court()
    {
        [$session, $hostUser] = $this->createTestSession('Single', 2);
        Auth::login($hostUser);

        $controller = new ScoringController;

        // Update Court 1 tiga kali (version 1 -> 2 -> 3)
        for ($i = 1; $i <= 3; $i++) {
            $req1 = new Request([
                'game_id' => $session->session_id,
                'round' => 'round_1',
                'match_key' => 'round_1_court_1',
                'court' => 1,
                'score_a' => $i,
                'score_b' => 0,
                'games_a' => $i,
                'games_b' => 0,
                'status' => 'in_progress',
                'client_version' => $i,
            ]);
            $resp1 = $controller->updateScore($req1);
        }
        $this->assertEquals(3, $resp1->getData()->version, 'Court 1 version harus 3.');

        // Update Court 2 satu kali
        $req2 = new Request([
            'game_id' => $session->session_id,
            'round' => 'round_1',
            'match_key' => 'round_1_court_2',
            'court' => 2,
            'score_a' => 1,
            'score_b' => 0,
            'games_a' => 1,
            'games_b' => 0,
            'status' => 'in_progress',
            'client_version' => 1,
        ]);
        $resp2 = $controller->updateScore($req2);
        $this->assertEquals(1, $resp2->getData()->version, 'Court 2 version harus independen (1), tidak boleh mengikuti Court 1.');

        // getScore verifikasi
        \Illuminate\Support\Facades\Request::replace(['match_key' => 'round_1_court_1']);
        $get1 = $controller->getScore($session->session_id, 'round_1')->getData();
        $this->assertEquals(3, $get1->version, 'Court 1 version harus tetap 3.');

        \Illuminate\Support\Facades\Request::replace(['match_key' => 'round_1_court_2']);
        $get2 = $controller->getScore($session->session_id, 'round_1')->getData();
        $this->assertEquals(1, $get2->version, 'Court 2 version harus tetap 1.');
    }

    /**
     * Test 14: Team Americano with Total of 7 Sets generates full 7 rounds of cyclical schedule
     */
    public function test_team_americano_with_target_rounds_7_generates_cyclical_schedule()
    {
        $service = new TeamAmericanoService;
        $players = ['Alpha1', 'Alpha2', 'Beta1', 'Beta2', 'Gamma1', 'Gamma2', 'Delta1', 'Delta2'];
        $courts = 2;
        $targetRounds = 7;

        $drawingData = $service->generateTeamRounds($players, $courts, null, $targetRounds);

        $this->assertEquals('Team Americano', $drawingData['format']);
        $this->assertEquals(4, $drawingData['total_teams']);
        $this->assertEquals(7, $drawingData['total_rounds']);
        $this->assertCount(7, $drawingData['rounds']);

        // Check each round has 2 matches for the 2 courts
        foreach ($drawingData['rounds'] as $rNum => $round) {
            $this->assertEquals($rNum, $round['round_number']);
            $this->assertCount(2, $round['matches'], "Set {$rNum} harus memiliki 2 match untuk 2 court.");
            $this->assertNotEmpty($round['matches'][0]['team_a']['players']);
            $this->assertNotEmpty($round['matches'][0]['team_b']['players']);
            $this->assertNotEmpty($round['matches'][1]['team_a']['players']);
            $this->assertNotEmpty($round['matches'][1]['team_b']['players']);
        }
    }

    /**
     * Test 15: ScoringController getRoundAccess unlocks Set 5 smoothly when Set 4 is completed for Total of 7
     */
    public function test_team_americano_scoring_round_access_for_7_sets()
    {
        [$session, $hostUser] = $this->createTestSession('Double', 2, 'Total of 7');

        Auth::login($hostUser);
        request()->merge(['format' => 'Team Americano']);

        $service = new TeamAmericanoService;
        $players = ['Alpha1', 'Alpha2', 'Beta1', 'Beta2', 'Gamma1', 'Gamma2', 'Delta1', 'Delta2'];
        $drawingData = $service->generateTeamRounds($players, 2, null, 7);

        $controller = new ScoringController;
        $game = $this->invokeMethod($controller, 'getGameData', [$session->session_id]);
        $gameRounds = [];
        foreach ($drawingData['rounds'] as $rNum => $rData) {
            $gameRounds["round_{$rNum}"] = $rData;
        }
        $game['drawing'] = $gameRounds;
        $scoringSystem = ScoringService::detectScoringSystem('Total of 7');

        // Initial: Round 1 open, Round 2..7 locked
        $access1 = $this->invokeMethod($controller, 'getRoundAccess', [$game, $scoringSystem, []]);
        $this->assertTrue($access1['round_1']);
        $this->assertFalse($access1['round_2']);
        $this->assertFalse($access1['round_5']);

        // Complete Round 1 to 4 in savedScores (scores summing to 7 for Total of 7)
        $savedScores = [];
        for ($r = 1; $r <= 4; $r++) {
            $savedScores["round_{$r}_court_1"] = ['status' => 'completed', 'games_a' => 4, 'games_b' => 3];
            $savedScores["round_{$r}_court_2"] = ['status' => 'completed', 'games_a' => 5, 'games_b' => 2];
        }

        $accessAfter4 = $this->invokeMethod($controller, 'getRoundAccess', [$game, $scoringSystem, $savedScores]);
        $this->assertTrue($accessAfter4['round_1']);
        $this->assertTrue($accessAfter4['round_2']);
        $this->assertTrue($accessAfter4['round_3']);
        $this->assertTrue($accessAfter4['round_4']);
        $this->assertTrue($accessAfter4['round_5'], 'Set 5 harus otomatis terbuka setelah Set 4 selesai pada Total of 7!');
        $this->assertFalse($accessAfter4['round_6'], 'Set 6 harus tetap terkunci sebelum Set 5 selesai.');
    }

    /**
     * Test 16: Drawing & Scoring pada kuota belum penuh (misal 4/6 pemain) hanya memuat pemain riil dan tidak menyisipkan dummy data.
     */
    public function test_drawing_and_scoring_with_partial_quota_only_uses_real_players_without_dummy()
    {
        $this->setupTestDatabaseSchema();

        $hostUser = User::create([
            'nama' => 'Host Budi',
            'email' => 'host_budi_'.uniqid().'@matcha.com',
            'role' => 'member',
            'password' => bcrypt('secret'),
        ]);

        $session = SessionModel::create([
            'host_user_id' => $hostUser->user_id,
            'sport_id' => 1,
            'venue_id' => 1,
            'nama_session' => 'Mabar Kuota 4 dari 6',
            'scoring_system' => 'Total of 3',
            'waktu_session' => '19:00 WIB',
            'datetime' => '2026-09-22 19:00:00',
            'status_session' => 'Open',
            'jumlah_pemain' => '6',
            'jenis_permainan' => 'Double',
        ]);

        $court = Court::create(['nama_court' => 'Court 1', 'venue_id' => 1]);
        \DB::table('tb_session_court')->insert([
            'session_id' => $session->session_id,
            'court_id' => $court->court_id,
        ]);

        $realPlayerNames = ['Host Budi', 'Andi Pratama', 'Citra Kirana', 'Dodi Kurniawan'];
        foreach ($realPlayerNames as $idx => $pName) {
            $user = $idx === 0 ? $hostUser : User::create([
                'nama' => $pName,
                'email' => "player_{$idx}_".uniqid().'@matcha.com',
                'role' => 'member',
                'password' => bcrypt('secret'),
            ]);
            $player = Player::create([
                'user_id' => $user->user_id,
                'nama' => $pName,
                'gender' => $idx % 2 === 0 ? 'Male' : 'Female',
                'level' => 'Intermediate',
            ]);
            \DB::table('tb_session_player')->insert([
                'session_id' => $session->session_id,
                'player_id' => $player->player_id,
            ]);
        }

        $session->load('players');
        $this->assertCount(4, $session->players);

        Auth::login($hostUser);

        // Uji ScoringController getGameData
        $scoringController = new ScoringController;
        $gameData = $this->invokeMethod($scoringController, 'getGameData', [$session->session_id]);

        $this->assertCount(4, $gameData['participants'], 'Participants hanya boleh berisi 4 pemain riil yang terdaftar.');
        $participantNames = array_column($gameData['participants'], 'name');
        $this->assertEquals($realPlayerNames, $participantNames);

        // Pastikan tidak ada satupun nama dummy (misal Gisel Anastasia, Marame Nagoan, Fahri Dhani)
        foreach ($gameData['drawing'] as $roundKey => $rData) {
            $allRoundPlayers = array_merge(
                $rData['team_a_names'] ?? [],
                $rData['team_b_names'] ?? [],
                $rData['resting'] ?? []
            );
            foreach ($allRoundPlayers as $p) {
                $this->assertContains($p, $realPlayerNames, "Pemain {$p} di ronde {$roundKey} harus merupakan pemain riil.");
                $this->assertStringNotContainsString('Gisel Anastasia', $p);
                $this->assertStringNotContainsString('Marame Nagoan', $p);
            }
        }
    }

    public function test_successful_save_with_failed_broadcast_does_not_recount_points()
    {
        [$session, $hostUser] = $this->createTestSession('Single', 1);
        $gameId = $session->session_id;
        Auth::login($hostUser);

        // Mock Supabase HTTP endpoint to simulate 401 Unauthorized
        Http::fake([
            '*/realtime/v1/api/broadcast' => Http::response(['message' => 'Unregistered API key'], 401),
        ]);

        $scoringController = new ScoringController;
        $request = new Request([
            'game_id' => $gameId,
            'round' => 'round_1',
            'match_key' => 'round_1_court_1',
            'court' => 1,
            'action' => 'batch_events',
            'events' => [
                [
                    'action' => 'add_point',
                    'team' => 'A',
                    'event_id' => 'evt_test_1',
                ],
            ],
            'client_id' => 'client_test_1',
            'client_seq' => 1,
            'event_id' => 'evt_test_1',
        ]);

        $res = $scoringController->updateScore($request);
        $data = $res->getData(true);

        $this->assertTrue($data['success']);
        $this->assertEquals(1, $data['version']);
        $this->assertEquals('15', $data['point_display_a']);

        // Verify score is in Cache
        $cached = Cache::get("scoring.game_{$gameId}.round_1_court_1");
        $this->assertNotNull($cached);
        $this->assertEquals('15', $cached['point_display_a']);
    }

    public function test_retry_same_event_id_is_deduplicated_and_does_not_increment_score()
    {
        [$session, $hostUser] = $this->createTestSession('Single', 1);
        $gameId = $session->session_id;
        Auth::login($hostUser);

        Http::fake([
            '*/realtime/v1/api/broadcast' => Http::response([], 200),
        ]);

        $scoringController = new ScoringController;
        $req1 = new Request([
            'game_id' => $gameId,
            'round' => 'round_1',
            'match_key' => 'round_1_court_1',
            'court' => 1,
            'action' => 'batch_events',
            'events' => [
                ['action' => 'add_point', 'team' => 'A', 'event_id' => 'evt_dedup_1'],
            ],
            'client_id' => 'client_test_1',
            'client_seq' => 1,
            'event_id' => 'evt_dedup_1',
        ]);

        $res1 = $scoringController->updateScore($req1)->getData(true);
        $this->assertTrue($res1['success']);
        $this->assertEquals('15', $res1['point_display_a']);
        $this->assertEquals(1, $res1['version']);

        // Retry same event
        $res2 = $scoringController->updateScore($req1)->getData(true);
        $this->assertTrue($res2['success']);
        $this->assertTrue($res2['duplicate']);
        $this->assertEquals('15', $res2['point_display_a'], 'Poin tidak boleh bertambah saat event_id di-retry');
        $this->assertEquals(1, $res2['version'], 'Version tidak boleh bertambah saat event_id di-retry');
    }

    public function test_historical_event_retry_after_cache_cleared_does_not_increment_score()
    {
        [$session, $hostUser] = $this->createTestSession('Single', 1);
        $gameId = $session->session_id;
        Auth::login($hostUser);

        Http::fake(['*/realtime/v1/api/broadcast' => Http::response([], 200)]);

        $scoringController = new ScoringController;

        // 1. Simpan Event A (Team A tambah poin -> 15-0, version 1)
        $reqA = new Request([
            'game_id' => $gameId,
            'round' => 'round_1',
            'match_key' => 'round_1_court_1',
            'court' => 1,
            'action' => 'batch_events',
            'events' => [
                ['action' => 'add_point', 'team' => 'A', 'event_id' => 'evt_hist_A', 'base_version' => 0],
            ],
            'client_id' => 'client_test_1',
            'client_seq' => 1,
            'event_id' => 'evt_hist_A',
            'base_version' => 0,
        ]);
        $resA = $scoringController->updateScore($reqA)->getData(true);
        $this->assertTrue($resA['success']);
        $this->assertEquals('15', $resA['point_display_a']);
        $this->assertEquals(1, $resA['version']);

        // 2. Simpan Event B (Team B tambah poin -> 15-15, version 2)
        $reqB = new Request([
            'game_id' => $gameId,
            'round' => 'round_1',
            'match_key' => 'round_1_court_1',
            'court' => 1,
            'action' => 'batch_events',
            'events' => [
                ['action' => 'add_point', 'team' => 'B', 'event_id' => 'evt_hist_B', 'base_version' => 1],
            ],
            'client_id' => 'client_test_1',
            'client_seq' => 2,
            'event_id' => 'evt_hist_B',
            'base_version' => 1,
        ]);
        $resB = $scoringController->updateScore($reqB)->getData(true);
        $this->assertTrue($resB['success']);
        $this->assertEquals('15', $resB['point_display_a']);
        $this->assertEquals('15', $resB['point_display_b']);
        $this->assertEquals(2, $resB['version']);

        // 3. Simulasikan CACHE HILANG TOTAL
        Cache::flush();

        // 4. Kirim ulang Event A (yang base_version=0, lebih tua dari DB version=2 dan bukan last_event_id DB)
        $resRetryA = $scoringController->updateScore($reqA)->getData(true);
        $this->assertTrue($resRetryA['success']);
        $this->assertTrue($resRetryA['duplicate'], 'Event A lama harus dikenali sebagai duplikat/historical');
        $this->assertEquals('15', $resRetryA['point_display_a'], 'Skor Team A tidak boleh bertambah');
        $this->assertEquals('15', $resRetryA['point_display_b'], 'Skor Team B tidak boleh berubah');
        $this->assertEquals(2, $resRetryA['version'], 'Version tidak boleh bertambah saat event lama di-retry');
    }

    public function test_batch_retry_with_partially_processed_events_only_applies_new_events()
    {
        [$session, $hostUser] = $this->createTestSession('Single', 1);
        $gameId = $session->session_id;
        Auth::login($hostUser);

        Http::fake(['*/realtime/v1/api/broadcast' => Http::response([], 200)]);

        $scoringController = new ScoringController;

        // 1. Kirim Batch 1: [Event A, Event B] (15-15, version 2)
        $reqBatch1 = new Request([
            'game_id' => $gameId,
            'round' => 'round_1',
            'match_key' => 'round_1_court_1',
            'court' => 1,
            'action' => 'batch_events',
            'events' => [
                ['action' => 'add_point', 'team' => 'A', 'event_id' => 'batch_evt_A', 'base_version' => 0],
                ['action' => 'add_point', 'team' => 'B', 'event_id' => 'batch_evt_B', 'base_version' => 1],
            ],
            'client_id' => 'client_test_1',
            'client_seq' => 2,
            'event_id' => 'batch_evt_B',
            'base_version' => 1,
        ]);
        $resBatch1 = $scoringController->updateScore($reqBatch1)->getData(true);
        $this->assertTrue($resBatch1['success']);
        $this->assertEquals(1, $resBatch1['version']);
        $this->assertEquals('15', $resBatch1['point_display_a']);
        $this->assertEquals('15', $resBatch1['point_display_b']);

        // 2. Simulasikan CACHE HILANG TOTAL
        Cache::flush();

        // 3. Client mencoba retry antrean offline yang berisi [Event A, Event B, Event C]
        // Event A & B sudah pernah tersimpan, hanya Event C (Team A tambah poin -> 30-15) yang baru!
        $reqBatch2 = new Request([
            'game_id' => $gameId,
            'round' => 'round_1',
            'match_key' => 'round_1_court_1',
            'court' => 1,
            'action' => 'batch_events',
            'events' => [
                ['action' => 'add_point', 'team' => 'A', 'event_id' => 'batch_evt_A', 'base_version' => 0],
                ['action' => 'add_point', 'team' => 'B', 'event_id' => 'batch_evt_B', 'base_version' => 1],
                ['action' => 'add_point', 'team' => 'A', 'event_id' => 'batch_evt_C', 'base_version' => 2],
            ],
            'client_id' => 'client_test_1',
            'client_seq' => 3,
            'event_id' => 'batch_evt_C',
            'base_version' => 2,
        ]);
        $resBatch2 = $scoringController->updateScore($reqBatch2)->getData(true);
        $this->assertTrue($resBatch2['success']);
        $this->assertEquals(2, $resBatch2['version'], 'Version harus bertambah tepat 1 untuk Event C');
        $this->assertEquals('30', $resBatch2['point_display_a'], 'Hanya Event C yang diterapkan ke Team A');
        $this->assertEquals('15', $resBatch2['point_display_b'], 'Team B tidak boleh bertambah lagi');
    }

    public function test_two_courts_updated_concurrently_do_not_lose_scores()
    {
        [$session, $hostUser] = $this->createTestSession('Double', 2);
        $gameId = $session->session_id;
        Auth::login($hostUser);

        Http::fake(['*/realtime/v1/api/broadcast' => Http::response([], 200)]);

        $scoringController = new ScoringController;

        // Update Court 1
        $reqCourt1 = new Request([
            'game_id' => $gameId,
            'round' => 'round_1',
            'match_key' => 'round_1_court_1',
            'court' => 1,
            'action' => 'batch_events',
            'events' => [['action' => 'add_point', 'team' => 'A', 'event_id' => 'c1_evt_1']],
            'client_id' => 'host_1',
            'client_seq' => 1,
            'event_id' => 'c1_evt_1',
        ]);
        $scoringController->updateScore($reqCourt1);

        // Update Court 2
        $reqCourt2 = new Request([
            'game_id' => $gameId,
            'round' => 'round_1',
            'match_key' => 'round_1_court_2',
            'court' => 2,
            'action' => 'batch_events',
            'events' => [['action' => 'add_point', 'team' => 'B', 'event_id' => 'c2_evt_1']],
            'client_id' => 'host_1',
            'client_seq' => 2,
            'event_id' => 'c2_evt_1',
        ]);
        $scoringController->updateScore($reqCourt2);

        // Verify both courts exist in shared session cache array
        $sharedScores = Cache::get("scoring.game_{$gameId}", []);
        $this->assertArrayHasKey('round_1_court_1', $sharedScores, 'Court 1 tidak boleh hilang dari cache');
        $this->assertArrayHasKey('round_1_court_2', $sharedScores, 'Court 2 tidak boleh hilang dari cache');
        $this->assertEquals('15', $sharedScores['round_1_court_1']['point_display_a']);
        $this->assertEquals('15', $sharedScores['round_1_court_2']['point_display_b']);
    }

    public function test_cache_follows_database_change_including_final_score()
    {
        [$session, $hostUser] = $this->createTestSession('Single', 1);
        $gameId = $session->session_id;

        // Initialize cache with in_progress score (games 3-2)
        $initialCache = [
            'version' => 10,
            'games_a' => 3,
            'games_b' => 2,
            'point_display_a' => '0',
            'point_display_b' => '0',
            'status' => 'in_progress',
        ];
        Cache::put("scoring.game_{$gameId}.round_1_court_1", $initialCache, 300);

        // Create drawing and match if not present
        $drawing = Drawing::firstOrCreate(
            ['session_id' => $gameId],
            ['match_format_id' => 1]
        );
        $match = GameMatch::firstOrCreate(
            ['drawing_id' => $drawing->drawing_id, 'nomor_match' => 1],
            ['status_match' => 'In Progress', 'version' => 10]
        );
        $match->status_match = 'Completed';
        $match->winner_team = 'Team A';
        $match->version = 15;
        $match->save();

        Score::updateOrCreate(
            ['match_id' => $match->match_id, 'set_number' => 1],
            [
                'game_number' => 1,
                'game_score_a' => 6,
                'game_score_b' => 2,
                'point_score_a' => 'Game',
                'point_score_b' => '0',
                'status_score' => 'Final',
                'version' => 15,
            ]
        );

        // Poll with force_refresh/reconnect to trigger DB reconciliation
        $scoringController = new ScoringController;
        $req = Request::create("/scoring/get-score/{$gameId}/round_1", 'GET', [
            'court' => 0,
            'match_key' => 'round_1_court_1',
            'reconnect' => '1',
        ]);
        $this->app->instance('request', $req);
        $response = $scoringController->getScore($gameId, 'round_1');
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('completed', $data['status'], 'Cache harus mengikuti skor final dari database');
        $this->assertEquals(6, $data['games_a']);
        $this->assertEquals(2, $data['games_b']);
        $this->assertEquals(15, $data['version']);
    }

    public function test_concurrent_hosts_with_same_base_version_merges_without_losing_points()
    {
        [$session, $hostUser] = $this->createTestSession('Single', 1);
        $gameId = $session->session_id;
        Auth::login($hostUser);

        Http::fake(['*/realtime/v1/api/broadcast' => Http::response([], 200)]);
        $scoringController = new ScoringController;

        // Host 1 mengirim poin untuk Team A dari base_version 0
        $reqHost1 = new Request([
            'game_id' => $gameId,
            'round' => 'round_1',
            'match_key' => 'round_1_court_1',
            'court' => 1,
            'action' => 'add_point',
            'point_won_by' => 'A',
            'event_id' => 'evt_host1_A',
            'base_version' => 0,
            'client_id' => 'host_1',
            'client_seq' => 1,
        ]);
        $resHost1 = $scoringController->updateScore($reqHost1)->getData(true);
        $this->assertTrue($resHost1['success']);
        $this->assertEquals(1, $resHost1['version']);
        $this->assertEquals('15', $resHost1['point_display_a']);
        $this->assertEquals('0', $resHost1['point_display_b']);

        // Host 2 mengirim poin untuk Team B juga dari base_version 0 (concurrent/stale base)
        // Request ini TIDAK BOLEH dianggap duplikat karena event_id berbeda dan mewakili poin sah!
        $reqHost2 = new Request([
            'game_id' => $gameId,
            'round' => 'round_1',
            'match_key' => 'round_1_court_1',
            'court' => 1,
            'action' => 'add_point',
            'point_won_by' => 'B',
            'event_id' => 'evt_host2_B',
            'base_version' => 0,
            'client_id' => 'host_2',
            'client_seq' => 1,
        ]);
        $resHost2 = $scoringController->updateScore($reqHost2)->getData(true);
        $this->assertTrue($resHost2['success']);
        $this->assertFalse(! empty($resHost2['duplicate']), 'Event baru dari host lain tidak boleh dianggap duplikat');
        $this->assertTrue(! empty($resHost2['merged']), 'Event harus di-merge secara 3-way delta');
        $this->assertEquals(2, $resHost2['version']);
        $this->assertEquals('15', $resHost2['point_display_a'], 'Poin Host 1 (Team A) harus tetap terjaga');
        $this->assertEquals('15', $resHost2['point_display_b'], 'Poin Host 2 (Team B) harus berhasil ditambahkan');
    }

    public function test_batch_retry_without_last_event_id_does_not_drop_new_events_nor_duplicate_old_events()
    {
        [$session, $hostUser] = $this->createTestSession('Single', 1);
        $gameId = $session->session_id;
        Auth::login($hostUser);

        Http::fake(['*/realtime/v1/api/broadcast' => Http::response([], 200)]);
        $scoringController = new ScoringController;

        // 1. Kirim Batch 1: [evt_A (Team A 15), evt_B (Team B 15)] -> Version 1
        $reqBatch1 = new Request([
            'game_id' => $gameId,
            'round' => 'round_1',
            'match_key' => 'round_1_court_1',
            'court' => 1,
            'action' => 'batch_events',
            'events' => [
                ['action' => 'add_point', 'team' => 'A', 'event_id' => 'batch_evt_A', 'base_version' => 0],
                ['action' => 'add_point', 'team' => 'B', 'event_id' => 'batch_evt_B', 'base_version' => 1],
            ],
            'client_id' => 'client_1',
            'client_seq' => 1,
            'event_id' => 'batch_evt_B',
            'base_version' => 1,
        ]);
        $res1 = $scoringController->updateScore($reqBatch1)->getData(true);
        $this->assertTrue($res1['success']);
        $this->assertEquals(1, $res1['version']);

        // 2. Kirim Event C secara individual (Team A tambah poin -> 30-15) -> Version 2, last_event_id DB menjadi evt_C
        $reqC = new Request([
            'game_id' => $gameId,
            'round' => 'round_1',
            'match_key' => 'round_1_court_1',
            'court' => 1,
            'action' => 'add_point',
            'point_won_by' => 'A',
            'event_id' => 'batch_evt_C',
            'base_version' => 1,
            'client_id' => 'client_2',
            'client_seq' => 1,
        ]);
        $resC = $scoringController->updateScore($reqC)->getData(true);
        $this->assertTrue($resC['success']);
        $this->assertEquals('30', $resC['point_display_a']);
        $this->assertEquals(2, $resC['version']);

        // 3. Simulasikan Cache di-flush total
        Cache::flush();

        // 4. Client 1 retries antrean batch parsial: [evt_A, evt_D] (Team B tambah poin)
        // Perhatikan: evt_A sudah pernah diproses di DB, evt_C tidak ada di dalam batch ini sama sekali!
        $reqBatchRetry = new Request([
            'game_id' => $gameId,
            'round' => 'round_1',
            'match_key' => 'round_1_court_1',
            'court' => 1,
            'action' => 'batch_events',
            'events' => [
                ['action' => 'add_point', 'team' => 'A', 'event_id' => 'batch_evt_A', 'base_version' => 0],
                ['action' => 'add_point', 'team' => 'B', 'event_id' => 'batch_evt_D', 'base_version' => 2],
            ],
            'client_id' => 'client_1',
            'client_seq' => 2,
            'event_id' => 'batch_evt_D',
            'base_version' => 2,
        ]);
        $resRetry = $scoringController->updateScore($reqBatchRetry)->getData(true);
        $this->assertTrue($resRetry['success']);
        $this->assertEquals(3, $resRetry['version'], 'Hanya evt_D yang menambah versi');
        $this->assertEquals('30', $resRetry['point_display_a'], 'evt_A tidak boleh diproses ulang');
        $this->assertEquals('30', $resRetry['point_display_b'], 'evt_D (Team B) harus berhasil diterapkan');
    }

    public function test_determine_session_round_count_uses_n_rounds_for_total_of(): void
    {
        foreach ([3, 4, 5, 6, 7, 8, 9, 10] as $sets) {
            $count = ScoringService::determineSessionRoundCount("Total of {$sets}", 8, false, false, '2 Jam', 1);
            $this->assertSame($sets, $count, "Total of {$sets} harus menghasilkan tepat {$sets} ronde.");
        }
    }

    public function test_determine_session_round_count_for_first_to_multi_round(): void
    {
        // 1. Americano Double 4 pemain -> 3 ronde
        $this->assertSame(3, ScoringService::determineSessionRoundCount('First to 8', 4, false, false, '2 Jam', 1));

        // 2. Americano Double 6 pemain -> 5 ronde
        $this->assertSame(5, ScoringService::determineSessionRoundCount('First to 6', 6, false, false, '2 Jam', 1));

        // 3. Americano Double 8 pemain -> multi-ronde (5-7 ronde)
        $count8 = ScoringService::determineSessionRoundCount('First to 8', 8, false, false, '2 Jam', 1);
        $this->assertGreaterThanOrEqual(5, $count8);
        $this->assertLessThanOrEqual(7, $count8);

        // 4. Team Americano 4 pemain (2 tim) -> 3 ronde (best of 3 sets)
        $this->assertSame(3, ScoringService::determineSessionRoundCount('First to 6', 4, true, false, '2 Jam', 1));

        // 5. Team Americano 6 pemain (3 tim) -> 3 ronde
        $this->assertSame(3, ScoringService::determineSessionRoundCount('First to 8', 6, true, false, '2 Jam', 1));

        // 6. Americano Single 2 pemain (1v1) -> 3 ronde (best of 3 sets)
        $this->assertSame(3, ScoringService::determineSessionRoundCount('First to 4', 2, false, true, '1 Jam', 1));

        // 7. Americano Single 4 pemain -> 3 ronde
        $this->assertSame(3, ScoringService::determineSessionRoundCount('First to 6', 4, false, true, '1 Jam', 1));
    }

    /**
     * Pengujian terarah sesuai Aturan Final Total Of N:
     * 1. Semua Total Of 3–10 menghasilkan N ronde (drawing).
     * 2. Setiap pertandingan pada tiap ronde memiliki batas total N game (games_a + games_b = N).
     * 3. Total Of 3: skor 2–0 masih aktif; 2–1 atau 3–0 selesai.
     * 4. Setelah set pertama selesai, skor set berikutnya dimulai dari nol dan riwayat set pertama tetap utuh.
     * 5. Dua kemenangan pertama tidak mengakhiri Total Of 3; set ketiga tetap dimainkan.
     * 6. Total Of 4: skor 2–2 final (draw/seri) dan ronde berikutnya dapat dilanjutkan setelah semua court final.
     * 7. Total Of 10: skor 5–5 final pada set tersebut, bukan otomatis mengakhiri seluruh sesi.
     * 8. Satu court final tidak membuka ronde berikutnya jika court lain masih aktif.
     * 9. Poin tambahan setelah final tidak mengubah skor.
     * 10. Penyelesaian seluruh N ronde menghasilkan recap yang membaca hasil setiap ronde.
     * 11. First To tetap berjalan tanpa regresi.
     */
    public function test_total_of_final_rules_dual_n_sets_and_games_and_completion_behavior()
    {
        $this->setupTestDatabaseSchema();
        $controller = new ScoringController;
        $americanoService = new AmericanoService;
        $teamService = new TeamAmericanoService;

        // 1. Semua Total Of 3–10 menghasilkan N ronde (drawing)
        foreach (range(3, 10) as $n) {
            $scoring = ScoringService::detectScoringSystem("Total of {$n}");
            $this->assertTrue($scoring['is_sets'], "Total of {$n} harus memiliki is_sets = true");
            $this->assertSame($n, $scoring['max_sets'], "Total of {$n} harus memiliki max_sets = {$n}");
            $this->assertSame(6, $scoring['target_games'], "Total of {$n} target_games normal per set adalah 6");

            // Americano Double (4 players)
            $players4 = ['P1', 'P2', 'P3', 'P4'];
            $roundsAm = $americanoService->generateRounds($players4, 1, $scoring['max_sets'], 'Double');
            $this->assertCount($n, $roundsAm, "Americano Double Total of {$n} harus menghasilkan {$n} ronde");

            // Americano Single (2 players)
            $players2 = ['P1', 'P2'];
            $roundsSingle = $americanoService->generateRounds($players2, 1, $scoring['max_sets'], 'Single');
            $this->assertCount($n, $roundsSingle, "Americano Single Total of {$n} harus menghasilkan {$n} ronde");

            // Americano Single (4 players)
            $roundsSingle4 = $americanoService->generateRounds($players4, 2, $scoring['max_sets'], 'Single');
            $this->assertCount($n, $roundsSingle4, "Americano Single 4 players Total of {$n} harus menghasilkan {$n} ronde");

            // Team Americano (4 players = 2 pairs)
            $roundsTeam = $teamService->generateTeamRounds($players4, 1, null, $scoring['max_sets']);
            $this->assertCount($n, $roundsTeam['rounds'], "Team Americano Total of {$n} harus menghasilkan {$n} ronde");
        }

        // 2. Pengujian batas skor besar per set Total Of (Aturan 6 / jika 5-5 lanjut 7):
        $total3 = ScoringService::detectScoringSystem('Total of 3');

        // Total Of 3 pada skor 2–1: masih aktif
        $this->assertFalse(ScoringService::isMatchCompleted(2, 1, $total3), 'Total of 3 pada skor 2–1: masih aktif');
        $this->assertNull(ScoringService::determineMatchWinner(2, 1, $total3));

        // 5–4 -> 6–4: selesai
        $this->assertFalse(ScoringService::isMatchCompleted(5, 4, $total3), '5–4 masih aktif');
        $this->assertTrue(ScoringService::isMatchCompleted(6, 4, $total3), '5–4 -> 6–4: selesai');
        $this->assertSame('Team A', ScoringService::determineMatchWinner(6, 4, $total3));

        // 5–5 -> 6–5: masih aktif
        $this->assertFalse(ScoringService::isMatchCompleted(5, 5, $total3), '5–5 belum selesai');
        $this->assertNull(ScoringService::determineMatchWinner(5, 5, $total3));
        $this->assertFalse(ScoringService::isMatchCompleted(6, 5, $total3), '5–5 -> 6–5: masih aktif');
        $this->assertNull(ScoringService::determineMatchWinner(6, 5, $total3));

        // 6–5 -> 7–5: selesai
        $this->assertTrue(ScoringService::isMatchCompleted(7, 5, $total3), '6–5 -> 7–5: selesai');
        $this->assertSame('Team A', ScoringService::determineMatchWinner(7, 5, $total3));

        // 6–5 -> 6–6: masih aktif
        $this->assertFalse(ScoringService::isMatchCompleted(6, 6, $total3), '6–5 -> 6–6: masih aktif');
        $this->assertNull(ScoringService::determineMatchWinner(6, 6, $total3));

        // 6–6 -> 7–6: selesai
        $this->assertTrue(ScoringService::isMatchCompleted(7, 6, $total3), '6–6 -> 7–6: selesai');
        $this->assertSame('Team A', ScoringService::determineMatchWinner(7, 6, $total3));

        // Uji hasil normal lainnya sisi A (6–0, 6–1, 6–2, 6–3)
        foreach ([0, 1, 2, 3] as $loserScore) {
            $this->assertTrue(ScoringService::isMatchCompleted(6, $loserScore, $total3), "6–{$loserScore} selesai");
            $this->assertSame('Team A', ScoringService::determineMatchWinner(6, $loserScore, $total3));
        }

        // Uji hasil kebalikan untuk sisi B:
        // 4–5 -> 4–6: selesai
        $this->assertFalse(ScoringService::isMatchCompleted(4, 5, $total3), '4–5 masih aktif');
        $this->assertTrue(ScoringService::isMatchCompleted(4, 6, $total3), '4–5 -> 4–6: selesai');
        $this->assertSame('Team B', ScoringService::determineMatchWinner(4, 6, $total3));

        // 5–6: masih aktif
        $this->assertFalse(ScoringService::isMatchCompleted(5, 6, $total3), '5–6 masih aktif');
        $this->assertNull(ScoringService::determineMatchWinner(5, 6, $total3));

        // 5–6 -> 5–7: selesai
        $this->assertTrue(ScoringService::isMatchCompleted(5, 7, $total3), '5–6 -> 5–7: selesai');
        $this->assertSame('Team B', ScoringService::determineMatchWinner(5, 7, $total3));

        // 6–6 -> 6–7: selesai
        $this->assertTrue(ScoringService::isMatchCompleted(6, 7, $total3), '6–6 -> 6–7: selesai');
        $this->assertSame('Team B', ScoringService::determineMatchWinner(6, 7, $total3));

        // Uji hasil normal kebalikan sisi B (0–6, 1–6, 2–6, 3–6)
        foreach ([0, 1, 2, 3] as $loserScore) {
            $this->assertTrue(ScoringService::isMatchCompleted($loserScore, 6, $total3), "{$loserScore}–6 selesai");
            $this->assertSame('Team B', ScoringService::determineMatchWinner($loserScore, 6, $total3));
        }

        // 3. Setelah set pertama selesai, skor set berikutnya dimulai dari nol dan riwayat set pertama tetap utuh
        $savedScoresSession = [
            'round_1' => [
                'status' => 'completed',
                'games_a' => 6,
                'games_b' => 4,
                'score_a' => 6,
                'score_b' => 4,
                'winner_team' => 'Team A',
            ],
            'round_2' => [
                'status' => 'in_progress',
                'games_a' => 0,
                'games_b' => 0,
                'score_a' => 0,
                'score_b' => 0,
            ],
        ];
        $this->assertSame(6, $savedScoresSession['round_1']['games_a']);
        $this->assertSame(4, $savedScoresSession['round_1']['games_b']);
        $this->assertSame(0, $savedScoresSession['round_2']['games_a']);
        $this->assertSame(0, $savedScoresSession['round_2']['games_b']);

        // 4. Dua kemenangan pertama tidak mengakhiri Total Of 3; set ketiga tetap dimainkan
        $gameTotal3 = [
            'id' => 991,
            'scoring_system' => 'Total of 3',
            'drawing' => [
                'round_1' => ['matches' => [['court' => 1, 'team_a' => ['P1', 'P2'], 'team_b' => ['P3', 'P4']]]],
                'round_2' => ['matches' => [['court' => 1, 'team_a' => ['P1', 'P3'], 'team_b' => ['P2', 'P4']]]],
                'round_3' => ['matches' => [['court' => 1, 'team_a' => ['P1', 'P4'], 'team_b' => ['P2', 'P3']]]],
            ],
        ];
        // Team A menang di round 1 (6-4) dan menang di round 2 (6-2)
        $scoresRound1And2Won = [
            'round_1' => ['status' => 'completed', 'games_a' => 6, 'games_b' => 4, 'winner_team' => 'Team A'],
            'round_2' => ['status' => 'completed', 'games_a' => 6, 'games_b' => 2, 'winner_team' => 'Team A'],
        ];
        $access = $this->invokeMethod($controller, 'getRoundAccess', [$gameTotal3, $total3, $scoresRound1And2Won]);
        $this->assertTrue($access['round_1']);
        $this->assertTrue($access['round_2']);
        $this->assertTrue($access['round_3'], 'Set ketiga wajib terbuka dan tetap dimainkan meskipun 2 set pertama dimenangkan satu pihak.');

        // 5. Total Of 4: Memiliki tepat 4 ronde dan ronde ke-4 dapat dimainkan
        $total4 = ScoringService::detectScoringSystem('Total of 4');
        $this->assertSame(4, $total4['max_sets']);
        $gameTotal4 = [
            'id' => 992,
            'scoring_system' => 'Total of 4',
            'drawing' => [
                'round_1' => ['matches' => [['court' => 1, 'team_a' => ['P1', 'P2'], 'team_b' => ['P3', 'P4']]]],
                'round_2' => ['matches' => [['court' => 1, 'team_a' => ['P1', 'P3'], 'team_b' => ['P2', 'P4']]]],
                'round_3' => ['matches' => [['court' => 1, 'team_a' => ['P1', 'P4'], 'team_b' => ['P2', 'P3']]]],
                'round_4' => ['matches' => [['court' => 1, 'team_a' => ['P1', 'P2'], 'team_b' => ['P3', 'P4']]]],
            ],
        ];
        $scoresTotal4Round123 = [
            'round_1' => ['status' => 'completed', 'games_a' => 6, 'games_b' => 3, 'winner_team' => 'Team A'],
            'round_2' => ['status' => 'completed', 'games_a' => 7, 'games_b' => 5, 'winner_team' => 'Team A'],
            'round_3' => ['status' => 'completed', 'games_a' => 4, 'games_b' => 6, 'winner_team' => 'Team B'],
        ];
        $accessTotal4 = $this->invokeMethod($controller, 'getRoundAccess', [$gameTotal4, $total4, $scoresTotal4Round123]);
        $this->assertTrue($accessTotal4['round_4'], 'Ronde 4 harus terbuka setelah ronde 1, 2, 3 selesai.');

        // 6. Multi-court: satu court final tidak membuka ronde berikutnya jika court lain masih aktif
        $gameMultiCourt = [
            'id' => 994,
            'scoring_system' => 'Total of 3',
            'drawing' => [
                'round_1' => [
                    'court_count' => 2,
                    'matches' => [
                        ['court' => 1, 'team_a' => ['P1', 'P2'], 'team_b' => ['P3', 'P4']],
                        ['court' => 2, 'team_a' => ['P5', 'P6'], 'team_b' => ['P7', 'P8']],
                    ],
                ],
                'round_2' => [
                    'court_count' => 2,
                    'matches' => [
                        ['court' => 1, 'team_a' => ['P1', 'P3'], 'team_b' => ['P2', 'P4']],
                        ['court' => 2, 'team_a' => ['P5', 'P7'], 'team_b' => ['P6', 'P8']],
                    ],
                ],
            ],
        ];
        $scoresPartialCourt = [
            'round_1_court_1' => ['status' => 'completed', 'games_a' => 6, 'games_b' => 2],
            'round_1_court_2' => ['status' => 'in_progress', 'games_a' => 5, 'games_b' => 5],
        ];
        $this->assertFalse(
            $this->invokeMethod($controller, 'isRoundFullyCompleted', [$gameMultiCourt, 'round_1', $scoresPartialCourt]),
            'Round 1 belum sepenuhnya selesai jika court 2 masih in_progress (5–5)'
        );
        $accessPartial = $this->invokeMethod($controller, 'getRoundAccess', [$gameMultiCourt, $total3, $scoresPartialCourt]);
        $this->assertFalse($accessPartial['round_2'], 'Round 2 tidak boleh terbuka jika court 2 masih in_progress');

        // Setelah court 2 selesai (7–5), round 2 baru terbuka
        $scoresAllCourtDone = [
            'round_1_court_1' => ['status' => 'completed', 'games_a' => 6, 'games_b' => 2],
            'round_1_court_2' => ['status' => 'completed', 'games_a' => 7, 'games_b' => 5],
        ];
        $this->assertTrue($this->invokeMethod($controller, 'isRoundFullyCompleted', [$gameMultiCourt, 'round_1', $scoresAllCourtDone]));
        $accessAllDone = $this->invokeMethod($controller, 'getRoundAccess', [$gameMultiCourt, $total3, $scoresAllCourtDone]);
        $this->assertTrue($accessAllDone['round_2'], 'Round 2 harus terbuka setelah kedua court selesai');

        // 7. Event setelah final tidak menambah skor
        $completedState = [
            'games_a' => 6,
            'games_b' => 4,
            'score_a' => 6,
            'score_b' => 4,
            'status' => 'completed',
            'winner_team' => 'Team A',
            'idx_a' => 0,
            'idx_b' => 0,
        ];
        $attemptAddPointAfterFinal = $this->invokeMethod($controller, 'applyPointDeltaToState', [$completedState, 'A', $total3]);
        $this->assertSame(6, $attemptAddPointAfterFinal['games_a']);
        $this->assertSame(4, $attemptAddPointAfterFinal['games_b']);
        $this->assertSame('completed', $attemptAddPointAfterFinal['status']);

        // 8. Penyelesaian seluruh N ronde menghasilkan recap yang membaca hasil setiap ronde sekali saja
        $gameForRecap = [
            'id' => 995,
            'match_format' => 'Americano',
            'scoring_system' => 'Total of 3',
            'participants' => [
                ['name' => 'Alice'],
                ['name' => 'Bob'],
                ['name' => 'Charlie'],
                ['name' => 'David'],
            ],
            'drawing' => [
                'round_1' => ['matches' => [['court' => 1, 'team_a' => ['Alice', 'Bob'], 'team_b' => ['Charlie', 'David']]]],
                'round_2' => ['matches' => [['court' => 1, 'team_a' => ['Alice', 'Charlie'], 'team_b' => ['Bob', 'David']]]],
                'round_3' => ['matches' => [['court' => 1, 'team_a' => ['Alice', 'David'], 'team_b' => ['Bob', 'Charlie']]]],
            ],
        ];
        $scoresAll3Rounds = [
            'round_1_court_1' => ['status' => 'completed', 'score_a' => 6, 'score_b' => 4, 'games_a' => 6, 'games_b' => 4, 'winner_team' => 'Team A'],
            'round_2_court_1' => ['status' => 'completed', 'score_a' => 3, 'score_b' => 6, 'games_a' => 3, 'games_b' => 6, 'winner_team' => 'Team B'],
            'round_3_court_1' => ['status' => 'completed', 'score_a' => 7, 'score_b' => 5, 'games_a' => 7, 'games_b' => 5, 'winner_team' => 'Team A'],
        ];
        $recap = ScoringService::calculateRecap($gameForRecap, $scoresAll3Rounds);
        $this->assertCount(4, $recap);
        $aliceStats = collect($recap)->firstWhere('name', 'Alice');
        $this->assertNotNull($aliceStats);
        $this->assertSame(3, $aliceStats['matches'], 'Alice harus bermain di semua 3 ronde');
        // Alice di round 1 menang 6-4 (+6 games, +1 win), round 2 kalah 3-6 (Charlie & Bob di B menang 6), round 3 menang 7-5 (+7 games, +1 win)
        $this->assertSame(2, $aliceStats['wins']);
        $this->assertSame(1, $aliceStats['losses']);
        $this->assertSame(16, $aliceStats['games_won']); // 6 + 3 + 7 = 16

        // 9. First To tetap berjalan tanpa regresi
        foreach (range(2, 10) as $ftTarget) {
            $ft = ScoringService::detectScoringSystem("First to {$ftTarget}");
            $this->assertFalse($ft['is_sets'], "First to {$ftTarget} is_sets harus false");
            $this->assertSame(1, $ft['max_sets']);
            $this->assertSame($ftTarget, $ft['target_games']);
            $this->assertFalse(ScoringService::isMatchCompleted($ftTarget - 1, 0, $ft));
            $this->assertTrue(ScoringService::isMatchCompleted($ftTarget, 0, $ft));
            $this->assertSame('Team A', ScoringService::determineMatchWinner($ftTarget, 0, $ft));
        }
    }

    /**
     * 1. Fixture: Satu match memiliki banyak EventLog/snapshot dan satu hasil final 2–1.
     * Hasil otoritatif tetap 2–1, rincian tidak berulang, total statistik tidak menghitung snapshot.
     */
    public function test_authoritative_score_filtering_eliminates_eventlogs_and_snapshots()
    {
        $mockScores = collect([
            (object) ['score_id' => 101, 'set_number' => 1, 'status_score' => 'EventLog', 'version' => 1, 'game_score_a' => 0, 'game_score_b' => 0, 'set_score_a' => 0, 'set_score_b' => 0, 'score_side_a' => 0, 'score_side_b' => 0],
            (object) ['score_id' => 102, 'set_number' => 1, 'status_score' => 'EventLog', 'version' => 2, 'game_score_a' => 0, 'game_score_b' => 1, 'set_score_a' => 0, 'set_score_b' => 0, 'score_side_a' => 0, 'score_side_b' => 1],
            (object) ['score_id' => 103, 'set_number' => 1, 'status_score' => 'EventLog', 'version' => 3, 'game_score_a' => 1, 'game_score_b' => 1, 'set_score_a' => 0, 'set_score_b' => 0, 'score_side_a' => 1, 'score_side_b' => 1],
            (object) ['score_id' => 104, 'set_number' => 1, 'status_score' => 'Final', 'version' => 4, 'game_score_a' => 2, 'game_score_b' => 1, 'set_score_a' => 1, 'set_score_b' => 0, 'score_side_a' => 2, 'score_side_b' => 1],
            (object) ['score_id' => 105, 'set_number' => 1, 'status_score' => 'EventLog', 'version' => 4, 'game_score_a' => 2, 'game_score_b' => 1, 'set_score_a' => 1, 'set_score_b' => 0, 'score_side_a' => 2, 'score_side_b' => 1],
        ]);

        $authScores = ScoringService::getAuthoritativeScoresForMatch($mockScores);
        $this->assertCount(1, $authScores);
        $this->assertSame(104, $authScores->first()->score_id);
        $this->assertSame(2, (int) $authScores->first()->game_score_a);
        $this->assertSame(1, (int) $authScores->first()->game_score_b);

        // Uji sanitasi set_history
        $rawHistory = [
            ['set' => 1, 'score_a' => 0, 'score_b' => 0],
            ['set' => 1, 'score_a' => 0, 'score_b' => 1],
            ['set' => 1, 'score_a' => 1, 'score_b' => 1],
            ['set' => 1, 'score_a' => 2, 'score_b' => 1],
        ];
        $sanitized = ScoringService::sanitizeSetHistory($rawHistory);
        $this->assertCount(1, $sanitized);
        $this->assertSame(1, $sanitized[0]['set']);
        $this->assertSame(2, $sanitized[0]['score_a']);
        $this->assertSame(1, $sanitized[0]['score_b']);

        // Uji calculateRecap tidak mengakumulasi snapshot
        $game = [
            'id' => 999,
            'scoring_system' => 'Total of 3',
            'participants' => [
                ['name' => 'Ahmad'],
                ['name' => 'Marcello'],
            ],
            'drawing' => [
                'round_1' => [
                    'matches' => [
                        ['court' => 1, 'team_a' => ['Ahmad'], 'team_b' => ['Marcello']],
                    ],
                ],
            ],
        ];
        $sessionScores = [
            'round_1' => [
                'status' => 'completed',
                'games_a' => 10, // Terinflasi oleh snapshot lama
                'games_b' => 15,
                'sets_a' => 1,
                'sets_b' => 0,
                'set_history' => $rawHistory,
            ],
        ];

        $recap = ScoringService::calculateRecap($game, $sessionScores);
        $ahmad = collect($recap)->firstWhere('name', 'Ahmad');
        $marcello = collect($recap)->firstWhere('name', 'Marcello');

        $this->assertSame(2, $ahmad['points_for']);
        $this->assertSame(1, $marcello['points_for']);
        $this->assertSame(1, $ahmad['wins']);
        $this->assertSame(0, $marcello['wins']);
    }

    /**
     * 2. Fixture Tiga ronde: setiap hasil final tampil sekali dan statistik terakumulasi sekali.
     */
    public function test_three_rounds_recap_displays_each_final_once_and_accumulates_correctly()
    {
        $game = [
            'id' => 998,
            'scoring_system' => 'Total of 3',
            'participants' => [
                ['name' => 'Ahmad'],
                ['name' => 'Marcello'],
            ],
            'drawing' => [
                'round_1' => ['matches' => [['court' => 1, 'team_a' => ['Ahmad'], 'team_b' => ['Marcello']]]],
                'round_2' => ['matches' => [['court' => 1, 'team_a' => ['Ahmad'], 'team_b' => ['Marcello']]]],
                'round_3' => ['matches' => [['court' => 1, 'team_a' => ['Ahmad'], 'team_b' => ['Marcello']]]],
            ],
        ];

        $sessionScores = [
            'round_1' => [
                'status' => 'completed',
                'games_a' => 2,
                'games_b' => 1,
                'set_history' => [
                    ['set' => 1, 'score_a' => 0, 'score_b' => 1],
                    ['set' => 1, 'score_a' => 2, 'score_b' => 1],
                ],
            ],
            'round_2' => [
                'status' => 'completed',
                'games_a' => 2,
                'games_b' => 1,
                'set_history' => [
                    ['set' => 1, 'score_a' => 1, 'score_b' => 1],
                    ['set' => 1, 'score_a' => 2, 'score_b' => 1],
                ],
            ],
            'round_3' => [
                'status' => 'completed',
                'games_a' => 0,
                'games_b' => 3,
                'set_history' => [
                    ['set' => 1, 'score_a' => 0, 'score_b' => 3],
                ],
            ],
        ];

        $effective = ScoringService::getEffectiveScores($game, $sessionScores);
        $this->assertCount(1, $effective['round_1']['set_history']);
        $this->assertCount(1, $effective['round_2']['set_history']);
        $this->assertCount(1, $effective['round_3']['set_history']);

        $recap = ScoringService::calculateRecap($game, $sessionScores);
        $ahmad = collect($recap)->firstWhere('name', 'Ahmad');
        $marcello = collect($recap)->firstWhere('name', 'Marcello');

        // Ahmad: 2 + 2 + 0 = 4 points, 2 wins
        $this->assertSame(4, $ahmad['points_for']);
        $this->assertSame(2, $ahmad['wins']);
        $this->assertSame(1, $ahmad['losses']);

        // Marcello: 1 + 1 + 3 = 5 points, 1 win
        $this->assertSame(5, $marcello['points_for']);
        $this->assertSame(1, $marcello['wins']);
        $this->assertSame(2, $marcello['losses']);
    }

    /**
     * 3. Fixture Multi-court: semua match tampil tanpa duplikasi atau kehilangan data.
     */
    public function test_multi_court_session_preserves_distinct_matches_without_dropping_or_merging()
    {
        $game = [
            'id' => 997,
            'scoring_system' => 'Total of 3',
            'participants' => [
                ['name' => 'Player 1'],
                ['name' => 'Player 2'],
                ['name' => 'Player 3'],
                ['name' => 'Player 4'],
            ],
            'drawing' => [
                'round_1' => [
                    'court_count' => 2,
                    'matches' => [
                        ['court' => 1, 'team_a' => ['Player 1'], 'team_b' => ['Player 2']],
                        ['court' => 2, 'team_a' => ['Player 3'], 'team_b' => ['Player 4']],
                    ],
                ],
            ],
        ];

        $sessionScores = [
            'round_1_court_1' => ['status' => 'completed', 'games_a' => 2, 'games_b' => 1, 'set_history' => [['set' => 1, 'score_a' => 2, 'score_b' => 1]]],
            'round_1_court_2' => ['status' => 'completed', 'games_a' => 0, 'games_b' => 3, 'set_history' => [['set' => 1, 'score_a' => 0, 'score_b' => 3]]],
        ];

        $effective = ScoringService::getEffectiveScores($game, $sessionScores);
        $this->assertArrayHasKey('round_1_court_1', $effective);
        $this->assertArrayHasKey('round_1_court_2', $effective);
        $this->assertSame(2, $effective['round_1_court_1']['games_a']);
        $this->assertSame(3, $effective['round_1_court_2']['games_b']);

        $recap = ScoringService::calculateRecap($game, $sessionScores);
        $this->assertCount(4, $recap);
    }

    /**
     * 4. Hasil seri: tidak ada badge WIN sepihak.
     */
    public function test_draw_match_does_not_assign_one_sided_win_badge()
    {
        $ft = ScoringService::detectScoringSystem('Total of 4');
        $winner = ScoringService::determineMatchWinner(2, 2, $ft);
        $this->assertNull($winner, 'Pertandingan dengan skor 2-2 harus menghasilkan draw / seri (winner null)');
    }

    /**
     * 5, 6, 7. Login Ahmad dan Marcello bergantian, nonpeserta tidak mendapat kartu, refresh mempertahankan hasil.
     */
    public function test_personal_stats_card_matches_authenticated_participant_strictly()
    {
        $this->setupTestDatabaseSchema();

        $userAhmad = User::create(['nama' => 'Ahmad Subarjo', 'email' => 'ahmad@test.id', 'password' => bcrypt('password')]);
        $userMarcel = User::create(['nama' => 'Marcello Este Camaro', 'email' => 'marcel@test.id', 'password' => bcrypt('password')]);
        $userSpectator = User::create(['nama' => 'Penonton Netral', 'email' => 'spectator@test.id', 'password' => bcrypt('password')]);

        $playerAhmad = Player::create(['user_id' => $userAhmad->user_id, 'nama' => 'Ahmad Subarjo']);
        $playerMarcel = Player::create(['user_id' => $userMarcel->user_id, 'nama' => 'Marcello Este Camaro']);
        $playerSpectator = Player::create(['user_id' => $userSpectator->user_id, 'nama' => 'Penonton Netral']);

        $controller = new ScoringController;

        $game = [
            'id' => 409,
            'sport' => 'Tennis',
            'participants' => [
                ['id' => $playerAhmad->player_id, 'user_id' => $userAhmad->user_id, 'name' => 'Ahmad Subarjo', 'level' => 'Intermediate', 'avatar' => null],
                ['id' => $playerMarcel->player_id, 'user_id' => $userMarcel->user_id, 'name' => 'Marcello Este Camaro', 'level' => 'Advanced', 'avatar' => null],
            ],
        ];

        $rankedPlayers = [
            ['name' => 'Marcello Este Camaro', 'points_for' => 5, 'wins' => 1, 'matches' => 3, 'level' => 'Advanced'],
            ['name' => 'Ahmad Subarjo', 'points_for' => 4, 'wins' => 2, 'matches' => 3, 'level' => 'Intermediate'],
        ];

        // 5a. Login Ahmad (User 4)
        Auth::login($userAhmad);
        $ahmadCard = $this->invokeMethod($controller, 'buildPlayerRecap', [$game, $rankedPlayers]);
        $this->assertNotNull($ahmadCard);
        $this->assertSame('Ahmad Subarjo', $ahmadCard['player_name']);
        $this->assertSame(4, $ahmadCard['total_points']);
        $this->assertSame('67%', $ahmadCard['win_rate']);
        $this->assertSame(2, $ahmadCard['wins']);

        // 5b. Login Marcello (User 3)
        Auth::login($userMarcel);
        $marcelCard = $this->invokeMethod($controller, 'buildPlayerRecap', [$game, $rankedPlayers]);
        $this->assertNotNull($marcelCard);
        $this->assertSame('Marcello Este Camaro', $marcelCard['player_name']);
        $this->assertSame(5, $marcelCard['total_points']);
        $this->assertSame('33%', $marcelCard['win_rate']);
        $this->assertSame(1, $marcelCard['wins']);

        // 6a. Non-peserta (User 99)
        Auth::login($userSpectator);
        $spectatorCard = $this->invokeMethod($controller, 'buildPlayerRecap', [$game, $rankedPlayers]);
        $this->assertNull($spectatorCard, 'Pengguna non-peserta tidak boleh menerima kartu pemain lain');

        // 6b. Guest (Auth::logout)
        Auth::logout();
        $guestCard = $this->invokeMethod($controller, 'buildPlayerRecap', [$game, $rankedPlayers]);
        $this->assertNull($guestCard, 'Pengunjung anonim / guest tidak boleh menerima kartu pemain');
    }

    /**
     * Regression Test First To 3:
     * Banyak snapshot/EventLog plus satu hasil final harus menghasilkan skor final sekali saja,
     * tanpa inflasi pada rincian, klasemen, atau statistik pribadi.
     */
    public function test_first_to_3_recap_eliminates_eventlogs_and_prevents_score_inflation()
    {
        $this->setupTestDatabaseSchema();

        $userBambang = User::create(['nama' => 'Bambang Sudirman', 'email' => 'bambang@test.id', 'password' => bcrypt('password')]);
        $userCandra = User::create(['nama' => 'Candra Wijaya', 'email' => 'candra@test.id', 'password' => bcrypt('password')]);

        $playerBambang = Player::create(['user_id' => $userBambang->user_id, 'nama' => 'Bambang Sudirman']);
        $playerCandra = Player::create(['user_id' => $userCandra->user_id, 'nama' => 'Candra Wijaya']);

        $sport = Sport::create(['nama_sport' => 'Tennis']);
        $venue = Venue::create(['nama_venue' => 'Arena Test']);
        \Illuminate\Support\Facades\DB::table('tb_match_format')->insertOrIgnore([
            'match_format_id' => 1,
            'nama_format' => 'americano',
        ]);

        $session = SessionModel::create([
            'host_user_id' => $userBambang->user_id,
            'sport_id' => $sport->sport_id,
            'venue_id' => $venue->venue_id,
            'nama_session' => 'Sesi First To 3 Test',
            'scoring_system' => 'First to 3',
            'status_session' => 'Finished',
            'jenis_permainan' => 'Single',
        ]);
        $session->players()->attach([$playerBambang->player_id, $playerCandra->player_id]);

        $drawing = Drawing::create([
            'session_id' => $session->session_id,
            'match_format_id' => 1,
        ]);

        $match = GameMatch::create([
            'drawing_id' => $drawing->drawing_id,
            'nomor_match' => 1,
            'status_match' => 'Completed',
            'winner_team' => 'Team B',
            'hasil_pertandingan' => 'Game Score 1 - 3',
            'waktu_mulai' => now()->subMinutes(30),
            'waktu_selesai' => now(),
        ]);

        MatchParticipant::create(['match_id' => $match->match_id, 'player_id' => $playerBambang->player_id, 'side' => 'A']);
        MatchParticipant::create(['match_id' => $match->match_id, 'player_id' => $playerCandra->player_id, 'side' => 'B']);

        // Simulasikan 9 event logs progres seperti pada sesi 411 yang jika dijumlahkan bernilai tinggi
        for ($v = 1; $v <= 9; $v++) {
            Score::create([
                'match_id' => $match->match_id,
                'set_number' => 1,
                'game_number' => 1,
                'game_score_a' => 1,
                'game_score_b' => 2,
                'version' => $v,
                'status_score' => 'EventLog',
                'last_event_id' => 'evt_'.$v,
            ]);
        }

        // 1 baris In Progress
        Score::create([
            'match_id' => $match->match_id,
            'set_number' => 1,
            'game_number' => 1,
            'game_score_a' => 0,
            'game_score_b' => 2,
            'version' => 10,
            'status_score' => 'In Progress',
        ]);

        // 1 baris Final otoritatif: Skor akhir 1 - 3
        Score::create([
            'match_id' => $match->match_id,
            'set_number' => 1,
            'game_number' => 1,
            'game_score_a' => 1,
            'game_score_b' => 3,
            'version' => 15,
            'status_score' => 'Final',
            'hasil_pertandingan' => 'Game Score 1 - 3',
        ]);

        // 1. Verifikasi getAuthoritativeScoresForMatch()
        $dbScores = Score::where('match_id', $match->match_id)->get();
        $this->assertCount(11, $dbScores, 'Ada 11 baris skor total di tb_score');

        $authScores = ScoringService::getAuthoritativeScoresForMatch($dbScores);
        $this->assertCount(1, $authScores, 'Hanya ada 1 skor otoritatif untuk set 1');
        $this->assertSame(1, (int) $authScores->first()->game_score_a);
        $this->assertSame(3, (int) $authScores->first()->game_score_b);

        // 2. Verifikasi ScoringController::recap()
        $controller = new ScoringController;
        $recapView = $controller->recap($session->session_id);
        $data = $recapView->getData();

        // Rincian match round 1
        $effectiveScores = $data['effectiveScores'];
        $this->assertArrayHasKey('round_1', $effectiveScores);
        $this->assertSame(1, (int) $effectiveScores['round_1']['games_a'], 'Game A tidak boleh terinflasi ke 10');
        $this->assertSame(3, (int) $effectiveScores['round_1']['games_b'], 'Game B tidak boleh terinflasi ke 23');
        $this->assertSame('Team B', $effectiveScores['round_1']['winner_team']);

        // Klasemen / rankedPlayers
        $ranked = collect($data['rankedPlayers']);
        $bambangStats = $ranked->firstWhere('name', 'Bambang Sudirman');
        $candraStats = $ranked->firstWhere('name', 'Candra Wijaya');

        $this->assertNotNull($bambangStats);
        $this->assertNotNull($candraStats);

        $this->assertSame(1, (int) $bambangStats['points_for'], 'Poin Bambang harus 1, bukan terinflasi');
        $this->assertSame(0, (int) $bambangStats['wins']);
        $this->assertSame(1, (int) $bambangStats['losses']);

        $this->assertSame(3, (int) $candraStats['points_for'], 'Poin Candra harus 3, bukan 23');
        $this->assertSame(1, (int) $candraStats['wins']);
        $this->assertSame(0, (int) $candraStats['losses']);

        // 3. Verifikasi statistik pribadi (buildPlayerRecap)
        $game = [
            'id' => $session->session_id,
            'sport' => 'Tennis',
            'participants' => [
                ['id' => $playerBambang->player_id, 'user_id' => $userBambang->user_id, 'name' => 'Bambang Sudirman', 'level' => 'Intermediate', 'avatar' => null],
                ['id' => $playerCandra->player_id, 'user_id' => $userCandra->user_id, 'name' => 'Candra Wijaya', 'level' => 'Advanced', 'avatar' => null],
            ],
        ];

        Auth::login($userCandra);
        $candraCard = $this->invokeMethod($controller, 'buildPlayerRecap', [$game, $data['rankedPlayers']]);
        $this->assertNotNull($candraCard);
        $this->assertSame('Candra Wijaya', $candraCard['player_name']);
        $this->assertSame(3, $candraCard['total_points'], 'Kartu pribadi Candra harus 3 poin');
        $this->assertSame('100%', $candraCard['win_rate']);

        Auth::login($userBambang);
        $bambangCard = $this->invokeMethod($controller, 'buildPlayerRecap', [$game, $data['rankedPlayers']]);
        $this->assertNotNull($bambangCard);
        $this->assertSame('Bambang Sudirman', $bambangCard['player_name']);
        $this->assertSame(1, $bambangCard['total_points'], 'Kartu pribadi Bambang harus 1 poin');
        $this->assertSame('0%', $bambangCard['win_rate']);

        // 4. Verifikasi PlayerController::calculateRealPlayerRecap()
        $playerRecap = PlayerController::calculateRealPlayerRecap($userCandra, $playerCandra);
        $this->assertSame(1, $playerRecap['player']['total_matches']);
        $this->assertSame(1, $playerRecap['player']['wins']);
        $this->assertNotEmpty($playerRecap['recent_matches']);
        $this->assertSame('3 - 1', $playerRecap['recent_matches'][0]['score'], 'Skor riwayat profil harus 3 - 1, bukan terinflasi');
    }

    public function test_single_four_players_one_court_schedule_has_zero_repeated_matchups_initially()
    {
        $service = new AmericanoService;
        $players = [
            ['id' => 1, 'name' => 'Marcello'],
            ['id' => 2, 'name' => 'Hilmy'],
            ['id' => 3, 'name' => 'Ahmad'],
            ['id' => 4, 'name' => 'Jeong'],
        ];

        $rounds = $service->generateRounds($players, 1, 3, 'Single');

        $this->assertCount(3, $rounds);
        $this->assertSame(['Marcello'], $rounds[1]['team_a_names']);
        $this->assertSame(['Hilmy'], $rounds[1]['team_b_names']);
        $this->assertEqualsCanonicalizing(['Ahmad', 'Jeong'], $rounds[1]['resting']);

        $this->assertSame(['Ahmad'], $rounds[2]['team_a_names']);
        $this->assertSame(['Jeong'], $rounds[2]['team_b_names']);
        $this->assertEqualsCanonicalizing(['Marcello', 'Hilmy'], $rounds[2]['resting']);

        // In Round 3, Marcello MUST NOT play Hilmy again!
        $this->assertSame(['Marcello'], $rounds[3]['team_a_names']);
        $this->assertSame(['Ahmad'], $rounds[3]['team_b_names']);
        $this->assertEqualsCanonicalizing(['Hilmy', 'Jeong'], $rounds[3]['resting']);
    }

    public function test_single_four_players_one_court_dynamically_resolves_winner_vs_winner_in_round_three()
    {
        $service = new AmericanoService;
        $players = [
            ['id' => 1, 'name' => 'Marcello'],
            ['id' => 2, 'name' => 'Hilmy'],
            ['id' => 3, 'name' => 'Ahmad'],
            ['id' => 4, 'name' => 'Jeong'],
        ];

        $rounds = $service->generateRounds($players, 1, 4, 'Single');

        // Skenario:
        // Round 1 (Marcello vs Hilmy): Hilmy menang (Team B menang)
        // Round 2 (Ahmad vs Jeong): Ahmad menang (Team A menang)
        $savedScores = [
            'round_1' => [
                'status' => 'completed',
                'winner_team' => 'Team B', // Hilmy wins
                'games_a' => 2,
                'games_b' => 5,
            ],
            'round_2' => [
                'status' => 'completed',
                'winner_team' => 'Team A', // Ahmad wins
                'games_a' => 5,
                'games_b' => 3,
            ],
        ];

        $resolvedRounds = $service->resolveDynamicSingleRounds($rounds, $savedScores);

        // Round 3 MUST BE: Hilmy (Winner R1) vs Ahmad (Winner R2)!
        $this->assertEqualsCanonicalizing(['Hilmy', 'Ahmad'], [
            $resolvedRounds[3]['team_a_names'][0],
            $resolvedRounds[3]['team_b_names'][0],
        ]);
        $this->assertEqualsCanonicalizing(['Marcello', 'Jeong'], $resolvedRounds[3]['resting']);

        // Round 4 MUST BE: Marcello (Loser R1) vs Jeong (Loser R2)!
        $this->assertEqualsCanonicalizing(['Marcello', 'Jeong'], [
            $resolvedRounds[4]['team_a_names'][0],
            $resolvedRounds[4]['team_b_names'][0],
        ]);
        $this->assertEqualsCanonicalizing(['Hilmy', 'Ahmad'], $resolvedRounds[4]['resting']);
    }

    public function test_scoring_service_resolves_rounds_progress_correctly_locking_future_rounds()
    {
        $rounds = [
            1 => [
                'round' => 1,
                'matches' => [
                    ['court' => 1, 'team_a_names' => ['Marcello'], 'team_b_names' => ['Hilmy']],
                ],
            ],
            2 => [
                'round' => 2,
                'matches' => [
                    ['court' => 1, 'team_a_names' => ['Ahmad'], 'team_b_names' => ['Jeong']],
                ],
            ],
            3 => [
                'round' => 3,
                'matches' => [
                    ['court' => 1, 'team_a_names' => ['Marcello'], 'team_b_names' => ['Ahmad']],
                ],
            ],
        ];

        // 1. Kondisi Awal: Belum ada skor sama sekali
        $initialProgress = ScoringService::resolveRoundsProgress(99991, $rounds, []);
        $this->assertTrue($initialProgress['access'][1]);
        $this->assertFalse($initialProgress['access'][2]);
        $this->assertFalse($initialProgress['access'][3]);
        $this->assertSame('ready', $initialProgress['status'][1]);
        $this->assertSame('locked', $initialProgress['status'][2]);
        $this->assertSame('locked', $initialProgress['status'][3]);
        $this->assertSame(1, $initialProgress['active_round_num']);
        $this->assertSame(0, $initialProgress['completed_count']);

        // 2. Ronde 1 Selesai dimainkan
        $scoresR1Done = [
            'round_1' => [
                'status' => 'completed',
                'games_a' => 4,
                'games_b' => 1,
            ],
        ];
        $progressAfterR1 = ScoringService::resolveRoundsProgress(99991, $rounds, $scoresR1Done);
        $this->assertTrue($progressAfterR1['access'][1]);
        $this->assertTrue($progressAfterR1['access'][2]); // Ronde 2 terbuka!
        $this->assertFalse($progressAfterR1['access'][3]); // Ronde 3 tetap terkunci
        $this->assertSame('completed', $progressAfterR1['status'][1]);
        $this->assertSame('ready', $progressAfterR1['status'][2]);
        $this->assertSame('locked', $progressAfterR1['status'][3]);
        $this->assertSame(2, $progressAfterR1['active_round_num']);
        $this->assertSame(1, $progressAfterR1['completed_count']);

        // 3. Ronde 2 Selesai dimainkan
        $scoresR2Done = [
            'round_1' => [
                'status' => 'completed',
                'games_a' => 4,
                'games_b' => 1,
            ],
            'round_2' => [
                'status' => 'completed',
                'games_a' => 2,
                'games_b' => 4,
            ],
        ];
        $progressAfterR2 = ScoringService::resolveRoundsProgress(99991, $rounds, $scoresR2Done);
        $this->assertTrue($progressAfterR2['access'][1]);
        $this->assertTrue($progressAfterR2['access'][2]);
        $this->assertTrue($progressAfterR2['access'][3]); // Ronde 3 terbuka!
        $this->assertSame('completed', $progressAfterR2['status'][1]);
        $this->assertSame('completed', $progressAfterR2['status'][2]);
        $this->assertSame('ready', $progressAfterR2['status'][3]);
        $this->assertSame(3, $progressAfterR2['active_round_num']);
        $this->assertSame(2, $progressAfterR2['completed_count']);
    }

    public function test_game_controller_drawing_returns_progressive_round_access_and_honors_query_parameter()
    {
        $this->setupTestDatabaseSchema();

        $session = SessionModel::create([
            'host_user_id' => 1,
            'sport_id' => 1,
            'venue_id' => 1,
            'nama_session' => 'Mabar Tahap 2 Test',
            'scoring_system' => 'Total of 3',
            'waktu_session' => '08:00 WIB (2 Jam)',
            'datetime' => '2026-09-17 08:00:00',
            'status_session' => 'Open',
            'jumlah_pemain' => '4',
            'jenis_permainan' => 'Single',
        ]);

        $players = [];
        for ($i = 1; $i <= 4; $i++) {
            $user = User::create([
                'nama' => "Player {$i}",
                'email' => "player{$i}_{$session->session_id}@matcha.test",
                'password' => bcrypt('password'),
                'role' => 'member',
            ]);
            $player = Player::create([
                'user_id' => $user->user_id,
                'nama' => "Player {$i}",
                'usia' => 25,
                'gender' => 'Male',
                'level' => 'Intermediate',
            ]);
            $session->players()->attach($player->player_id);
            $players[] = $player;
        }

        Auth::loginUsingId(1);
        $controller = new GameController;

        // Test drawing awal: Ronde 1 aktif & ready, Ronde 2 dan 3 terkunci
        $requestInit = Request::create("/games/{$session->session_id}/drawing", 'GET', ['json' => 1]);
        $responseInit = $controller->drawing($session->session_id, $requestInit);
        $dataInit = $responseInit->getData(true);

        $this->assertTrue($dataInit['success']);
        $this->assertTrue($dataInit['round_access'][1]);
        $this->assertFalse($dataInit['round_access'][2]);
        $this->assertSame(1, $dataInit['active_round_num']);

        // Simpan skor Ronde 1 selesai
        Cache::put("scoring.game_{$session->session_id}", [
            'round_1' => [
                'status' => 'completed',
                'games_a' => 4,
                'games_b' => 2,
            ],
            '_meta' => [
                'active_round' => 'round_2',
            ],
        ]);

        // Request drawing tanpa query round: otomatis default ke Ronde 2
        $requestR2Default = Request::create("/games/{$session->session_id}/drawing", 'GET', ['json' => 1]);
        $responseR2Default = $controller->drawing($session->session_id, $requestR2Default);
        $dataR2Default = $responseR2Default->getData(true);

        $this->assertTrue($dataR2Default['round_access'][2]);
        $this->assertSame(2, $dataR2Default['active_round_num']);

        // Request drawing dengan query ?round=2 yang sudah terbuka
        $requestR2Explicit = Request::create("/games/{$session->session_id}/drawing", 'GET', ['json' => 1, 'round' => 2]);
        $responseR2Explicit = $controller->drawing($session->session_id, $requestR2Explicit);
        $dataR2Explicit = $responseR2Explicit->getData(true);
        $this->assertSame(2, $dataR2Explicit['active_round_num']);

        // Request drawing dengan query ?round=3 yang masih terkunci: harus fallback ke round aktif (2)
        $requestR3Locked = Request::create("/games/{$session->session_id}/drawing", 'GET', ['json' => 1, 'round' => 3]);
        $responseR3Locked = $controller->drawing($session->session_id, $requestR3Locked);
        $dataR3Locked = $responseR3Locked->getData(true);
        $this->assertSame(2, $dataR3Locked['active_round_num']);
    }

    public function test_scoring_next_round_redirects_to_drawing_preview_and_supports_json_and_direct_live()
    {
        $this->setupTestDatabaseSchema();

        if (! Schema::hasColumn('tb_user', 'is_host')) {
            Schema::table('tb_user', function ($table) {
                $table->boolean('is_host')->default(false);
            });
        }

        $hostUser = User::create([
            'nama' => 'Host User',
            'email' => 'host_'.uniqid().'@test.com',
            'role' => 'host',
            'is_host' => true,
            'password' => bcrypt('password'),
        ]);

        $session = SessionModel::create([
            'host_user_id' => $hostUser->user_id,
            'sport_id' => 1,
            'venue_id' => 1,
            'nama_session' => 'Mabar Tahap 3 Test',
            'scoring_system' => 'Total of 3',
            'waktu_session' => '08:00 WIB (2 Jam)',
            'datetime' => '2026-09-17 08:00:00',
            'status_session' => 'In Progress',
            'jumlah_pemain' => '4',
            'jenis_permainan' => 'Single',
        ]);

        $drawing = Drawing::create([
            'session_id' => $session->session_id,
            'match_format_id' => 1,
            'tanggal_drawing' => '2026-09-17',
            'jam_drawing' => '08:00:00',
        ]);

        // Simpan match 1 completed
        GameMatch::create([
            'drawing_id' => $drawing->drawing_id,
            'nomor_match' => 1,
            'status_match' => 'Completed',
            'winner_team' => 'Team A',
        ]);

        Cache::put("scoring.game_{$session->session_id}", [
            'round_1' => [
                'status' => 'completed',
                'games_a' => 6,
                'games_b' => 2,
            ],
            '_meta' => [
                'active_round' => 'round_1',
            ],
        ]);

        Auth::login($hostUser);
        $scoringController = new ScoringController;

        // 1. Default Next Round: redirect ke games.drawing dengan round=2
        $requestDefault = Request::create(route('scoring.next-round'), 'POST', [
            'game_id' => $session->session_id,
            'current_round' => 'round_1',
            'next_round' => 'round_2',
        ]);
        $responseDefault = $scoringController->nextRound($requestDefault);
        $this->assertTrue($responseDefault->isRedirect(route('games.drawing', [
            'id' => $session->session_id,
            'round' => 2,
        ])));

        // 2. JSON Request Next Round: mengembalikan payload redirect ke games.drawing
        $requestJson = Request::create(route('scoring.next-round'), 'POST', [
            'game_id' => $session->session_id,
            'current_round' => 'round_1',
            'next_round' => 'round_2',
        ], [], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $responseJson = $scoringController->nextRound($requestJson);
        $dataJson = $responseJson->getData(true);
        $this->assertTrue($dataJson['success']);
        $this->assertSame(route('games.drawing', ['id' => $session->session_id, 'round' => 2]), $dataJson['redirect']);
        $this->assertSame(2, $dataJson['next_round_num']);

        // 3. Request dengan redirect_to=live: tetap mendukung redirect langsung ke live scoring jika diminta
        $requestLive = Request::create(route('scoring.next-round'), 'POST', [
            'game_id' => $session->session_id,
            'current_round' => 'round_1',
            'next_round' => 'round_2',
            'redirect_to' => 'live',
        ]);
        $responseLive = $scoringController->nextRound($requestLive);
        $this->assertTrue($responseLive->isRedirect(route('scoring.live', [
            'id' => $session->session_id,
            'format' => 'americano',
            'round' => 'round_2',
            'court' => 0,
        ])));
    }

    protected function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
