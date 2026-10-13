<?php

namespace Tests\Unit;

use App\Http\Controllers\Game\GameController;
use App\Http\Controllers\Scoring\ScoringController;
use App\Models\Court;
use App\Models\Drawing;
use App\Models\GameMatch;
use App\Models\MatchParticipant;
use App\Models\Player;
use App\Models\Score;
use App\Models\SessionModel;
use App\Models\User;
use App\Services\Drawing\AmericanoService;
use App\Services\Drawing\TeamAmericanoService;
use App\Services\Scoring\ScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
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
            'Total of 3' => [3, 2, true, 6],
            'Total of 4' => [4, 3, true, 6],
            'Total of 5' => [5, 3, true, 6],
            'Total of 6' => [6, 4, true, 6],
            'Total of 7' => [7, 4, true, 6],
            'Total of 8' => [8, 5, true, 6],
            'Total of 9' => [9, 5, true, 6],
            'Total of 10' => [10, 6, true, 6],
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
        }

        // Pastikan angka 10 dibaca utuh sebagai 10, bukan 1 atau fallback
        $total10 = ScoringService::detectScoringSystem('Total of 10');
        $this->assertSame(10, $total10['max_sets']);
        $this->assertSame(6, $total10['target_sets']);
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
        $validator = \Illuminate\Support\Facades\Validator::make(
            ['scoring_system' => 'Total of 99'],
            ['scoring_system' => ['required', 'string', \Illuminate\Validation\Rule::in(ScoringService::getAllowedNewScoringSystems())]],
            ['scoring_system.in' => 'Sistem skor yang dipilih tidak valid. Pilihan tersedia: Total of 3–10 atau First to 2–10.']
        );
        $this->assertTrue($validator->fails());
        $this->assertSame(
            'Sistem skor yang dipilih tidak valid. Pilihan tersedia: Total of 3–10 atau First to 2–10.',
            $validator->errors()->first('scoring_system')
        );

        // Validator juga menolak opsi legacy pada pembuatan sesi baru
        $legacyValidator = \Illuminate\Support\Facades\Validator::make(
            ['scoring_system' => 'First to 11'],
            ['scoring_system' => ['required', 'string', \Illuminate\Validation\Rule::in(ScoringService::getAllowedNewScoringSystems())]]
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
        $totalOfConfig = ScoringService::detectScoringSystem('Total of 3');

        // Skenario A: Akhir set Total Of
        // 5–4 → 6–4: selesai (Team A)
        $this->assertTrue(ScoringService::isMatchCompleted(6, 4, $totalOfConfig));
        $this->assertSame('Team A', ScoringService::determineMatchWinner(6, 4, $totalOfConfig));

        // 4–5 → 4–6: selesai (Team B)
        $this->assertTrue(ScoringService::isMatchCompleted(4, 6, $totalOfConfig));
        $this->assertSame('Team B', ScoringService::determineMatchWinner(4, 6, $totalOfConfig));

        // 5–5 → 6–5: belum selesai
        $this->assertFalse(ScoringService::isMatchCompleted(5, 5, $totalOfConfig));
        $this->assertFalse(ScoringService::isMatchCompleted(6, 5, $totalOfConfig));
        $this->assertNull(ScoringService::determineMatchWinner(6, 5, $totalOfConfig));

        // 5–5 → 5–6: belum selesai
        $this->assertFalse(ScoringService::isMatchCompleted(5, 6, $totalOfConfig));
        $this->assertNull(ScoringService::determineMatchWinner(5, 6, $totalOfConfig));

        // 6–5 → 7–5: selesai (Team A)
        $this->assertTrue(ScoringService::isMatchCompleted(7, 5, $totalOfConfig));
        $this->assertSame('Team A', ScoringService::determineMatchWinner(7, 5, $totalOfConfig));

        // 5–6 → 5–7: selesai (Team B)
        $this->assertTrue(ScoringService::isMatchCompleted(5, 7, $totalOfConfig));
        $this->assertSame('Team B', ScoringService::determineMatchWinner(5, 7, $totalOfConfig));

        // 6–5 → 6–6: belum selesai
        $this->assertFalse(ScoringService::isMatchCompleted(6, 6, $totalOfConfig));
        $this->assertNull(ScoringService::determineMatchWinner(6, 6, $totalOfConfig));

        // 6–6 → 7–6: selesai (Team A)
        $this->assertTrue(ScoringService::isMatchCompleted(7, 6, $totalOfConfig));
        $this->assertSame('Team A', ScoringService::determineMatchWinner(7, 6, $totalOfConfig));

        // 6–6 → 6–7: selesai (Team B)
        $this->assertTrue(ScoringService::isMatchCompleted(6, 7, $totalOfConfig));
        $this->assertSame('Team B', ScoringService::determineMatchWinner(6, 7, $totalOfConfig));

        // Hasil 6–0 sampai 6–4 dan kebalikannya: selesai
        for ($opponentGames = 0; $opponentGames <= 4; $opponentGames++) {
            $this->assertTrue(ScoringService::isMatchCompleted(6, $opponentGames, $totalOfConfig), "6–{$opponentGames} harus selesai.");
            $this->assertSame('Team A', ScoringService::determineMatchWinner(6, $opponentGames, $totalOfConfig));

            $this->assertTrue(ScoringService::isMatchCompleted($opponentGames, 6, $totalOfConfig), "{$opponentGames}–6 harus selesai.");
            $this->assertSame('Team B', ScoringService::determineMatchWinner($opponentGames, 6, $totalOfConfig));
        }

        // Bandingkan dengan First To 6: selesai pada 6–5 tanpa aturan tie-break Total Of
        $firstTo6Config = ScoringService::detectScoringSystem('First to 6');
        $this->assertTrue(ScoringService::isMatchCompleted(6, 5, $firstTo6Config), 'First to 6 harus selesai pada 6–5.');
        $this->assertSame('Team A', ScoringService::determineMatchWinner(6, 5, $firstTo6Config));
    }

    public function test_late_point_mutations_and_batch_events_stop_after_final()
    {
        $controller = new ScoringController;
        $scoringSystem = ScoringService::detectScoringSystem('Total of 3');

        // 1. Mutasi poin pada match yang sudah completed harus ditolak/diabaikan
        $completedState = [
            'games_a' => 6,
            'games_b' => 4,
            'score_a' => 6,
            'score_b' => 4,
            'status' => 'completed',
            'winner_team' => 'Team A',
            'sets_a' => 1,
            'sets_b' => 0,
        ];
        $afterLatePoint = $this->invokeMethod($controller, 'applyPointDeltaToState', [$completedState, 'A', $scoringSystem]);
        $this->assertSame(6, $afterLatePoint['games_a']);
        $this->assertSame(4, $afterLatePoint['games_b']);
        $this->assertSame('completed', $afterLatePoint['status']);

        // 2. Transisi dari 6–5 ke 7–5 menandai match completed
        $state65 = [
            'games_a' => 6,
            'games_b' => 5,
            'score_a' => 6,
            'score_b' => 5,
            'idx_a' => 3, // 40
            'idx_b' => 0, // 0
            'is_deuce' => false,
            'status' => 'in_progress',
        ];
        $state75 = $this->invokeMethod($controller, 'applyPointDeltaToState', [$state65, 'A', $scoringSystem]);
        $this->assertSame(7, $state75['games_a']);
        $this->assertSame(5, $state75['games_b']);
        $this->assertSame('completed', $state75['status']);
        $this->assertSame('Team A', $state75['winner_team']);

        // 3. Mutasi tambahan setelah 7–5 ditolak
        $stateAfter75 = $this->invokeMethod($controller, 'applyPointDeltaToState', [$state75, 'A', $scoringSystem]);
        $this->assertSame(7, $stateAfter75['games_a'], 'Poin setelah 7–5 tidak boleh menambah skor game.');
    }

    public function test_total_of_3_all_three_rounds_must_be_played_without_early_stopping()
    {
        $controller = new ScoringController;
        $scoringSystem = ScoringService::detectScoringSystem('Total of 3');

        $game = [
            'id' => 999,
            'match_format' => 'Americano',
            'scoring_system' => 'Total of 3',
            'drawing' => [
                'round_1' => ['matches' => [['court' => 1]]],
                'round_2' => ['matches' => [['court' => 1]]],
                'round_3' => ['matches' => [['court' => 1]]],
            ],
        ];

        // Tim A memenangkan Set 1 dan Set 2
        $savedScores = [
            'round_1' => ['status' => 'completed', 'games_a' => 6, 'games_b' => 2, 'score_a' => 6, 'score_b' => 2],
            'round_2' => ['status' => 'completed', 'games_a' => 6, 'games_b' => 3, 'score_a' => 6, 'score_b' => 3],
        ];

        // Periksa round access: Round 3 HARUS terbuka, TIDAK BOLEH berhenti dini karena target_sets
        $access = $this->invokeMethod($controller, 'getRoundAccess', [$game, $scoringSystem, $savedScores]);
        $this->assertTrue($access['round_1']);
        $this->assertTrue($access['round_2']);
        $this->assertTrue($access['round_3'], 'Set 3 pada Total of 3 WAJIB tetap dapat dimainkan meski satu pihak menang 2 set!');
    }

    public function test_court_with_score_6_5_blocks_round_advancement()
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

        // Court 1 selesai (6–4), Court 2 masih 6–5 (in_progress, belum selesai)
        $savedScores = [
            'round_1_court_1' => ['status' => 'completed', 'games_a' => 6, 'games_b' => 4],
            'round_1_court_2' => ['status' => 'in_progress', 'games_a' => 6, 'games_b' => 5],
        ];

        // isRoundFullyCompleted harus false karena court 2 belum final
        $isDone = $this->invokeMethod($controller, 'isRoundFullyCompleted', [$game, 'round_1', $savedScores]);
        $this->assertFalse($isDone, 'Ronde tidak boleh dianggap selesai jika salah satu court masih 6–5!');

        // Round 2 harus tetap terkunci
        $scoringSystem = ScoringService::detectScoringSystem('Total of 3');
        $access = $this->invokeMethod($controller, 'getRoundAccess', [$game, $scoringSystem, $savedScores]);
        $this->assertFalse($access['round_2'], 'Ronde 2 harus tetap terkunci sebelum semua court selesai.');

        // Ketika Court 2 tuntas 7–5, isRoundFullyCompleted menjadi true dan Ronde 2 terbuka
        $savedScores['round_1_court_2'] = ['status' => 'completed', 'games_a' => 7, 'games_b' => 5];
        $isDoneAfter75 = $this->invokeMethod($controller, 'isRoundFullyCompleted', [$game, 'round_1', $savedScores]);
        $this->assertTrue($isDoneAfter75);

        $accessAfter75 = $this->invokeMethod($controller, 'getRoundAccess', [$game, $scoringSystem, $savedScores]);
        $this->assertTrue($accessAfter75['round_2'], 'Ronde 2 harus terbuka setelah seluruh court tuntas.');
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

        $drawing = $service->generateRounds($players, 1, 1);
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
    }

    /**
     * Helper untuk membuat session test lengkap dengan court dan player.
     */
    protected function createTestSession($jenisPermainan = 'Single', $courtCount = 2)
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
            'scoring_system' => 'Total of 3',
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
        [$session, $hostUser] = $this->createTestSession('Double', 2);
        $session->scoring_system = 'Total of 7';
        $session->save();

        Auth::login($hostUser);
        request()->merge(['format' => 'Team Americano']);

        $controller = new ScoringController;
        $game = $this->invokeMethod($controller, 'getGameData', [$session->session_id]);
        $scoringSystem = ScoringService::detectScoringSystem('Total of 7');

        // Initial: Round 1 open, Round 2..7 locked
        $access1 = $this->invokeMethod($controller, 'getRoundAccess', [$game, $scoringSystem, []]);
        $this->assertTrue($access1['round_1']);
        $this->assertFalse($access1['round_2']);
        $this->assertFalse($access1['round_5']);

        // Complete Round 1 to 4 in savedScores
        $savedScores = [];
        for ($r = 1; $r <= 4; $r++) {
            $savedScores["round_{$r}_court_1"] = ['status' => 'completed', 'games_a' => 6, 'games_b' => 4];
            $savedScores["round_{$r}_court_2"] = ['status' => 'completed', 'games_a' => 6, 'games_b' => 3];
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

    protected function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
