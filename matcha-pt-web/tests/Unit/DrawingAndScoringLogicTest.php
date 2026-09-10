<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Drawing\AmericanoService;
use App\Services\Drawing\TeamAmericanoService;
use App\Services\Scoring\ScoringService;

class DrawingAndScoringLogicTest extends TestCase
{
    /**
     * Test 1: Americano Individual - Partner Rotation Across Sets.
     * Pastikan pasangan selalu berganti di tiap Set (Set 1, Set 2, Set 3).
     * Tidak ada pemain yang berpasangan dengan orang yang sama lebih dari sekali.
     */
    public function test_americano_partner_rotation_across_sets()
    {
        $service = new AmericanoService();
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
            $this->assertNotContains($pairA, $partnerPairs, "Team A di Round " . ($roundIndex + 1) . " ({$pairA}) tidak boleh mengulang pasangan sebelumnya.");
            $this->assertNotContains($pairB, $partnerPairs, "Team B di Round " . ($roundIndex + 1) . " ({$pairB}) tidak boleh mengulang pasangan sebelumnya.");

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
        $service = new AmericanoService();
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
        $service = new TeamAmericanoService();
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
}
