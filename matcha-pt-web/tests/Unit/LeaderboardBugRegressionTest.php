<?php

namespace Tests\Unit;

use App\Services\Scoring\ScoringService;
use PHPUnit\Framework\TestCase;

class LeaderboardBugRegressionTest extends TestCase
{
    /**
     * 1. Fixture merepresentasikan bug manual testing:
     * 2 pemain: scoups Edited (Player B) & Yoon Jeonghan (Player A).
     * 3 ronde, session finished.
     * Match status di tb_match masih 'In Progress' saat session finished.
     * R1: scoups Edited (B) menang 1-0 atas Yoon Jeonghan (A).
     * R2: Yoon Jeonghan (A) menang 1-0 atas scoups Edited (B).
     * R3: Yoon Jeonghan (A) menang 1-0 atas scoups Edited (B).
     */
    public function test_manual_testing_reproduction_2_players_3_rounds_in_progress_matches(): void
    {
        $game = [
            'id' => 635,
            'title' => 'Test leaderboard',
            'status' => 'Finished',
            'status_session' => 'Finished',
            'scoring_system' => 'First to 2 Poin',
            'participants' => [
                ['name' => 'Yoon Jeonghan', 'id' => 502],
                ['name' => 'scoups Edited', 'id' => 648],
            ],
            'drawing' => [
                'round_1' => [
                    'court' => 1,
                    'court_count' => 1,
                    'matches' => [
                        [
                            'court' => 1,
                            'team_a' => ['Yoon Jeonghan'],
                            'team_b' => ['scoups Edited'],
                        ],
                    ],
                ],
                'round_2' => [
                    'court' => 1,
                    'court_count' => 1,
                    'matches' => [
                        [
                            'court' => 1,
                            'team_a' => ['scoups Edited'],
                            'team_b' => ['Yoon Jeonghan'],
                        ],
                    ],
                ],
                'round_3' => [
                    'court' => 1,
                    'court_count' => 1,
                    'matches' => [
                        [
                            'court' => 1,
                            'team_a' => ['Yoon Jeonghan'],
                            'team_b' => ['scoups Edited'],
                        ],
                    ],
                ],
            ],
        ];

        // Status match masih 'in_progress' tetapi session berstatus 'finished'
        $sessionScores = [
            'round_1' => [
                'status' => 'in_progress',
                'score_a' => 0,
                'score_b' => 1,
                'games_a' => 0,
                'games_b' => 1,
            ],
            'round_2' => [
                'status' => 'in_progress',
                'score_a' => 0,
                'score_b' => 1,
                'games_a' => 0,
                'games_b' => 1,
            ],
            'round_3' => [
                'status' => 'in_progress',
                'score_a' => 1,
                'score_b' => 0,
                'games_a' => 1,
                'games_b' => 0,
            ],
        ];

        $recap = ScoringService::calculateRecap($game, $sessionScores);

        $this->assertCount(2, $recap);

        // Yoon Jeonghan menang di R2 dan R3 (total 2 kemenangan, 2 poin)
        $playerA = collect($recap)->firstWhere('name', 'Yoon Jeonghan');
        $this->assertNotNull($playerA);
        $this->assertSame(3, $playerA['matches']);
        $this->assertSame(2, $playerA['wins']);
        $this->assertSame(1, $playerA['losses']);
        $this->assertSame(2, $playerA['points_for']);
        $this->assertSame(1, $playerA['points_against']);
        $this->assertSame(1, $playerA['point_diff']);
        $this->assertSame(1, $playerA['rank'], 'Yoon Jeonghan harus Rank 1 (Juara 1)');

        // scoups Edited menang di R1 (total 1 kemenangan, 1 poin)
        $playerB = collect($recap)->firstWhere('name', 'scoups Edited');
        $this->assertNotNull($playerB);
        $this->assertSame(3, $playerB['matches']);
        $this->assertSame(1, $playerB['wins']);
        $this->assertSame(2, $playerB['losses']);
        $this->assertSame(1, $playerB['points_for']);
        $this->assertSame(2, $playerB['points_against']);
        $this->assertSame(-1, $playerB['point_diff']);
        $this->assertSame(2, $playerB['rank'], 'scoups Edited harus Rank 2');
    }

    /**
     * 2. Total Of dengan beberapa set.
     */
    public function test_total_of_multiple_sets(): void
    {
        $game = [
            'id' => 101,
            'status' => 'Finished',
            'scoring_system' => 'Total of 3',
            'participants' => [
                ['name' => 'Alice'],
                ['name' => 'Bob'],
            ],
            'drawing' => [
                'round_1' => [
                    'matches' => [
                        ['team_a' => ['Alice'], 'team_b' => ['Bob']],
                    ],
                ],
            ],
        ];

        $sessionScores = [
            'round_1' => [
                'status' => 'completed',
                'sets_a' => 2,
                'sets_b' => 1,
                'games_a' => 16,
                'games_b' => 15,
                'set_history' => [
                    ['set' => 1, 'score_a' => 6, 'score_b' => 4],
                    ['set' => 2, 'score_a' => 4, 'score_b' => 6],
                    ['set' => 3, 'score_a' => 6, 'score_b' => 5],
                ],
            ],
        ];

        $recap = ScoringService::calculateRecap($game, $sessionScores);
        $alice = collect($recap)->firstWhere('name', 'Alice');
        $bob = collect($recap)->firstWhere('name', 'Bob');

        $this->assertSame(1, $alice['matches']);
        $this->assertSame(1, $alice['wins']);
        $this->assertSame(16, $alice['points_for']);
        $this->assertSame(15, $alice['points_against']);
        $this->assertSame(1, $alice['rank']);

        $this->assertSame(1, $bob['matches']);
        $this->assertSame(0, $bob['wins']);
        $this->assertSame(15, $bob['points_for']);
        $this->assertSame(2, $bob['rank']);
    }

    /**
     * 3. First To Games format.
     */
    public function test_first_to_games_format(): void
    {
        $game = [
            'id' => 102,
            'status' => 'Finished',
            'scoring_system' => 'First to 8',
            'participants' => [
                ['name' => 'Player 1'],
                ['name' => 'Player 2'],
            ],
            'drawing' => [
                'round_1' => [
                    'matches' => [
                        ['team_a' => ['Player 1'], 'team_b' => ['Player 2']],
                    ],
                ],
            ],
        ];

        $sessionScores = [
            'round_1' => [
                'status' => 'completed',
                'games_a' => 8,
                'games_b' => 5,
            ],
        ];

        $recap = ScoringService::calculateRecap($game, $sessionScores);
        $p1 = collect($recap)->firstWhere('name', 'Player 1');
        $this->assertSame(1, $p1['matches']);
        $this->assertSame(1, $p1['wins']);
        $this->assertSame(8, $p1['points_for']);
        $this->assertSame(5, $p1['points_against']);
        $this->assertSame(1, $p1['rank']);
    }

    /**
     * 4. Match Stopped: Skor parsial dihitung, tidak menghasilkan fake winner.
     */
    public function test_match_stopped_partial_scores(): void
    {
        $game = [
            'id' => 103,
            'status' => 'Finished',
            'scoring_system' => 'First to 8',
            'participants' => [
                ['name' => 'Player A'],
                ['name' => 'Player B'],
            ],
            'drawing' => [
                'round_1' => [
                    'matches' => [
                        ['team_a' => ['Player A'], 'team_b' => ['Player B']],
                    ],
                ],
            ],
        ];

        $sessionScores = [
            'round_1' => [
                'status' => 'stopped',
                'games_a' => 5,
                'games_b' => 3,
            ],
        ];

        $recap = ScoringService::calculateRecap($game, $sessionScores);
        $pA = collect($recap)->firstWhere('name', 'Player A');
        $pB = collect($recap)->firstWhere('name', 'Player B');

        $this->assertSame(1, $pA['matches']);
        $this->assertSame(0, $pA['wins'], 'Stopped match tidak boleh memberikan match win');
        $this->assertSame(0, $pA['losses']);
        $this->assertSame(5, $pA['points_for']);

        $this->assertSame(1, $pB['matches']);
        $this->assertSame(0, $pB['wins']);
        $this->assertSame(0, $pB['losses']);
        $this->assertSame(3, $pB['points_for']);
    }

    /**
     * 5. Zero-state asli: Benar-benar belum ada pertandingan / skor.
     */
    public function test_true_zero_state_renders_rank_zero(): void
    {
        $game = [
            'id' => 104,
            'status' => 'In Progress',
            'scoring_system' => 'First to 8',
            'participants' => [
                ['name' => 'Player 1'],
                ['name' => 'Player 2'],
            ],
            'drawing' => [
                'round_1' => [
                    'matches' => [
                        ['team_a' => ['Player 1'], 'team_b' => ['Player 2']],
                    ],
                ],
            ],
        ];

        $sessionScores = [];

        $recap = ScoringService::calculateRecap($game, $sessionScores);
        foreach ($recap as $p) {
            $this->assertSame(0, $p['matches']);
            $this->assertSame(0, $p['wins']);
            $this->assertSame(0, $p['points_for']);
            $this->assertSame(0, $p['rank'], 'Zero-state harus rank 0');
            $this->assertSame('Belum Ada Hasil', $p['medal']['label']);
        }
    }

    /**
     * 6. Multi-court session.
     */
    public function test_multi_court_session_accumulation(): void
    {
        $game = [
            'id' => 105,
            'status' => 'Finished',
            'scoring_system' => 'First to 8',
            'participants' => [
                ['name' => 'P1'],
                ['name' => 'P2'],
                ['name' => 'P3'],
                ['name' => 'P4'],
            ],
            'drawing' => [
                'round_1' => [
                    'court_count' => 2,
                    'matches' => [
                        ['court' => 1, 'team_a' => ['P1'], 'team_b' => ['P2']],
                        ['court' => 2, 'team_a' => ['P3'], 'team_b' => ['P4']],
                    ],
                ],
            ],
        ];

        $sessionScores = [
            'round_1_court_1' => [
                'status' => 'completed',
                'games_a' => 8,
                'games_b' => 4,
            ],
            'round_1_court_2' => [
                'status' => 'completed',
                'games_a' => 8,
                'games_b' => 6,
            ],
        ];

        $recap = ScoringService::calculateRecap($game, $sessionScores);
        $this->assertCount(4, $recap);

        $p1 = collect($recap)->firstWhere('name', 'P1');
        $this->assertSame(1, $p1['matches']);
        $this->assertSame(8, $p1['points_for']);
        $this->assertSame(4, $p1['point_diff']);

        $p3 = collect($recap)->firstWhere('name', 'P3');
        $this->assertSame(1, $p3['matches']);
        $this->assertSame(8, $p3['points_for']);
        $this->assertSame(2, $p3['point_diff']);

        // P1 point_diff +4 lebih tinggi dari P3 (+2)
        $this->assertSame(1, $p1['rank']);
        $this->assertSame(2, $p3['rank']);
    }

    /**
     * 7. 5 Aturan tie-breaking: Total Poin -> Selisih Poin -> Wins -> Games Won -> Selisih Game.
     */
    public function test_five_business_rules_tie_breaking(): void
    {
        $game = [
            'id' => 106,
            'status' => 'Finished',
            'scoring_system' => 'Total of 3',
            'participants' => [
                ['name' => 'Alice'],
                ['name' => 'Bob'],
            ],
            'drawing' => [
                'round_1' => [
                    'matches' => [
                        ['team_a' => ['Alice'], 'team_b' => ['Bob']],
                    ],
                ],
            ],
        ];

        // Hasil identik persis
        $sessionScores = [
            'round_1' => [
                'status' => 'completed',
                'score_a' => 5,
                'score_b' => 5,
                'games_a' => 5,
                'games_b' => 5,
            ],
        ];

        $recap = ScoringService::calculateRecap($game, $sessionScores);
        $alice = collect($recap)->firstWhere('name', 'Alice');
        $bob = collect($recap)->firstWhere('name', 'Bob');

        $this->assertSame($alice['points_for'], $bob['points_for'], 'points_for equal');
        $this->assertSame($alice['point_diff'], $bob['point_diff'], 'point_diff equal');
        $this->assertSame($alice['wins'], $bob['wins'], 'wins equal');
        $this->assertSame($alice['games_won'], $bob['games_won'], 'games_won equal');
        $this->assertSame($alice['game_diff'], $bob['game_diff'], 'game_diff equal');
        $this->assertTrue(ScoringService::arePlayersTied($alice, $bob), 'arePlayersTied should be true');

        $this->assertSame(1, $alice['rank'], 'Joint Rank harus 1');
        $this->assertSame(1, $bob['rank'], 'Joint Rank harus 1');
    }
}
