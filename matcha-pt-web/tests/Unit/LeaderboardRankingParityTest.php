<?php

namespace Tests\Unit;

use App\Models\Drawing;
use App\Models\GameMatch;
use App\Models\Score;
use App\Models\SessionModel;
use App\Services\Scoring\ScoringService;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeaderboardRankingParityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setupTestDatabaseSchema();
    }

    protected function setupTestDatabaseSchema(): void
    {
        if (! Schema::hasTable('tb_session')) {
            Schema::create('tb_session', function ($table) {
                $table->id('session_id');
                $table->string('nama_session')->default('Session Test');
                $table->string('scoring_system')->default('Total of 3');
                $table->string('status_session')->default('Finished');
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('tb_drawing')) {
            Schema::create('tb_drawing', function ($table) {
                $table->id('drawing_id');
                $table->unsignedBigInteger('session_id');
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('tb_match')) {
            Schema::create('tb_match', function ($table) {
                $table->id('match_id');
                $table->unsignedBigInteger('drawing_id');
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('tb_score')) {
            Schema::create('tb_score', function ($table) {
                $table->id('score_id');
                $table->unsignedBigInteger('match_id');
                $table->integer('set_number')->default(1);
                $table->integer('score_side_a')->default(0);
                $table->integer('score_side_b')->default(0);
                $table->string('status_score')->nullable();
                $table->integer('version')->default(1);
                $table->timestamps();
            });
        }
    }

    // =========================================================================
    // 2. VERIFY CANONICAL SCORE ACROSS 10 SPECIFIC EDGE CASES
    // =========================================================================

    /** Case 1: Satu snapshot final */
    public function test_canonical_case_1_single_final_snapshot(): void
    {
        $match = GameMatch::create(['drawing_id' => 1]);
        Score::create(['match_id' => $match->match_id, 'set_number' => 1, 'score_side_a' => 6, 'score_side_b' => 4, 'status_score' => 'final', 'version' => 1]);

        $res = ScoringService::getAuthoritativeScoresForMatch(Score::where('match_id', $match->match_id)->get());
        $this->assertCount(1, $res);
        $this->assertEquals(6, $res->first()->score_side_a);
        $this->assertEquals(4, $res->first()->score_side_b);
        $this->assertEquals('final', $res->first()->status_score);
    }

    /** Case 2: Banyak eventlog disaring menjadi 1 snapshot otoritatif */
    public function test_canonical_case_2_many_eventlogs_filtered(): void
    {
        $match = GameMatch::create(['drawing_id' => 1]);
        for ($i = 1; $i <= 10; $i++) {
            Score::create(['match_id' => $match->match_id, 'set_number' => 1, 'score_side_a' => $i, 'score_side_b' => 0, 'status_score' => 'eventlog', 'version' => $i]);
        }
        Score::create(['match_id' => $match->match_id, 'set_number' => 1, 'score_side_a' => 11, 'score_side_b' => 0, 'status_score' => 'completed', 'version' => 11]);

        $res = ScoringService::getAuthoritativeScoresForMatch(Score::where('match_id', $match->match_id)->get());
        $this->assertCount(1, $res);
        $this->assertEquals(11, $res->first()->score_side_a);
        $this->assertEquals('completed', $res->first()->status_score);
    }

    /** Case 3: Multiple version untuk set yang sama mengambil versi terbaru */
    public function test_canonical_case_3_multiple_versions_picks_latest(): void
    {
        $match = GameMatch::create(['drawing_id' => 1]);
        Score::create(['match_id' => $match->match_id, 'set_number' => 1, 'score_side_a' => 6, 'score_side_b' => 4, 'status_score' => 'completed', 'version' => 1]);
        Score::create(['match_id' => $match->match_id, 'set_number' => 1, 'score_side_a' => 7, 'score_side_b' => 5, 'status_score' => 'completed', 'version' => 2]);

        $res = ScoringService::getAuthoritativeScoresForMatch(Score::where('match_id', $match->match_id)->get());
        $this->assertCount(1, $res);
        $this->assertEquals(7, $res->first()->score_side_a);
        $this->assertEquals(5, $res->first()->score_side_b);
        $this->assertEquals(2, $res->first()->version);
    }

    /** Case 4: Beberapa set dalam satu match tetap dihitung terpisah */
    public function test_canonical_case_4_multiple_sets_in_one_match(): void
    {
        $match = GameMatch::create(['drawing_id' => 1]);
        Score::create(['match_id' => $match->match_id, 'set_number' => 1, 'score_side_a' => 6, 'score_side_b' => 4, 'status_score' => 'completed', 'version' => 1]);
        Score::create(['match_id' => $match->match_id, 'set_number' => 2, 'score_side_a' => 3, 'score_side_b' => 6, 'status_score' => 'completed', 'version' => 1]);
        Score::create(['match_id' => $match->match_id, 'set_number' => 3, 'score_side_a' => 7, 'score_side_b' => 6, 'status_score' => 'completed', 'version' => 1]);

        $res = ScoringService::getAuthoritativeScoresForMatch(Score::where('match_id', $match->match_id)->get());
        $this->assertCount(3, $res);
        $this->assertEquals(6, $res->firstWhere('set_number', 1)->score_side_a);
        $this->assertEquals(3, $res->firstWhere('set_number', 2)->score_side_a);
        $this->assertEquals(7, $res->firstWhere('set_number', 3)->score_side_a);
    }

    /** Case 5: Match Completed */
    public function test_canonical_case_5_match_completed(): void
    {
        $match = GameMatch::create(['drawing_id' => 1]);
        Score::create(['match_id' => $match->match_id, 'set_number' => 1, 'score_side_a' => 21, 'score_side_b' => 18, 'status_score' => 'completed', 'version' => 5]);

        $res = ScoringService::getAuthoritativeScoresForMatch(Score::where('match_id', $match->match_id)->get());
        $this->assertCount(1, $res);
        $this->assertEquals('completed', $res->first()->status_score);
    }

    /** Case 6: Match Stopped dengan skor parsial (misal 5-3) */
    public function test_canonical_case_6_match_stopped_partial(): void
    {
        $match = GameMatch::create(['drawing_id' => 1]);
        Score::create(['match_id' => $match->match_id, 'set_number' => 1, 'score_side_a' => 5, 'score_side_b' => 3, 'status_score' => 'stopped', 'version' => 2]);

        $res = ScoringService::getAuthoritativeScoresForMatch(Score::where('match_id', $match->match_id)->get());
        $this->assertCount(1, $res);
        $this->assertEquals(5, $res->first()->score_side_a);
        $this->assertEquals(3, $res->first()->score_side_b);
        $this->assertEquals('stopped', $res->first()->status_score);
    }

    /** Case 7: Match Stopped pada skor 0-0 */
    public function test_canonical_case_7_match_stopped_zero_zero(): void
    {
        $match = GameMatch::create(['drawing_id' => 1]);
        Score::create(['match_id' => $match->match_id, 'set_number' => 1, 'score_side_a' => 0, 'score_side_b' => 0, 'status_score' => 'stopped', 'version' => 1]);

        $res = ScoringService::getAuthoritativeScoresForMatch(Score::where('match_id', $match->match_id)->get());
        $this->assertCount(1, $res);
        $this->assertEquals(0, $res->first()->score_side_a);
        $this->assertEquals(0, $res->first()->score_side_b);
        $this->assertEquals('stopped', $res->first()->status_score);
    }

    /** Case 8: Snapshot status null fallback */
    public function test_canonical_case_8_snapshot_status_null(): void
    {
        $match = GameMatch::create(['drawing_id' => 1]);
        Score::create(['match_id' => $match->match_id, 'set_number' => 1, 'score_side_a' => 4, 'score_side_b' => 2, 'status_score' => null, 'version' => 1]);

        $res = ScoringService::getAuthoritativeScoresForMatch(Score::where('match_id', $match->match_id)->get());
        $this->assertCount(1, $res);
        $this->assertEquals(4, $res->first()->score_side_a);
    }

    /** Case 9: Eventlog non-terminal dengan version lebih tinggi tidak mengalahkan snapshot terminal */
    public function test_canonical_case_9_terminal_beats_higher_version_eventlog(): void
    {
        $match = GameMatch::create(['drawing_id' => 1]);
        // Snapshot terminal completed pada version 3
        Score::create(['match_id' => $match->match_id, 'set_number' => 1, 'score_side_a' => 6, 'score_side_b' => 4, 'status_score' => 'completed', 'version' => 3]);
        // Eventlog late/stale dengan version 10
        Score::create(['match_id' => $match->match_id, 'set_number' => 1, 'score_side_a' => 6, 'score_side_b' => 5, 'status_score' => 'eventlog', 'version' => 10]);

        $res = ScoringService::getAuthoritativeScoresForMatch(Score::where('match_id', $match->match_id)->get());
        $this->assertCount(1, $res);
        // Baris completed harus menang karena non-eventlog dan terminal diprioritaskan
        $this->assertEquals(6, $res->first()->score_side_a);
        $this->assertEquals(4, $res->first()->score_side_b);
        $this->assertEquals('completed', $res->first()->status_score);
    }

    /** Case 10: Dua score row dengan version sama tetapi ID berbeda (ID lebih tinggi menang) */
    public function test_canonical_case_10_same_version_different_ids(): void
    {
        $match = GameMatch::create(['drawing_id' => 1]);
        $s1 = Score::create(['match_id' => $match->match_id, 'set_number' => 1, 'score_side_a' => 6, 'score_side_b' => 3, 'status_score' => 'completed', 'version' => 2]);
        $s2 = Score::create(['match_id' => $match->match_id, 'set_number' => 1, 'score_side_a' => 6, 'score_side_b' => 4, 'status_score' => 'completed', 'version' => 2]);

        $res = ScoringService::getAuthoritativeScoresForMatch(Score::where('match_id', $match->match_id)->get());
        $this->assertCount(1, $res);
        $this->assertEquals($s2->score_id, $res->first()->score_id);
        $this->assertEquals(4, $res->first()->score_side_b);
    }

    // =========================================================================
    // 3. VERIFY LEADERBOARD BUSINESS RULES & COMBINATIONS
    // =========================================================================

    /** RULE 1 & Fixture 1: Total Points takes precedence over Match Wins */
    public function test_rule_1_total_points_takes_precedence_over_match_wins(): void
    {
        $game = [
            'match_format' => 'Americano',
            'scoring_system' => 'First to 21',
            'participants' => [
                ['name' => 'Player A', 'user_id' => 1],
                ['name' => 'Player B', 'user_id' => 2],
                ['name' => 'Player C', 'user_id' => 3],
                ['name' => 'Player D', 'user_id' => 4],
            ],
            'drawing' => [
                'round_1' => ['team_a' => ['Player A'], 'team_b' => ['Player B']],
                'round_2' => ['team_a' => ['Player C'], 'team_b' => ['Player D']],
                'round_3' => ['team_a' => ['Player A'], 'team_b' => ['Player C']],
                'round_4' => ['team_a' => ['Player B'], 'team_b' => ['Player D']],
                'round_5' => ['team_a' => ['Player A'], 'team_b' => ['Player D']],
                'round_6' => ['team_a' => ['Player B'], 'team_b' => ['Player C']],
            ],
        ];

        $sessionScores = [
            'round_1' => ['status' => 'completed', 'games_a' => 11, 'games_b' => 10, 'score_a' => 11, 'score_b' => 10],
            'round_2' => ['status' => 'completed', 'games_a' => 15, 'games_b' => 5,  'score_a' => 15, 'score_b' => 5],
            'round_3' => ['status' => 'completed', 'games_a' => 11, 'games_b' => 10, 'score_a' => 11, 'score_b' => 10],
            'round_4' => ['status' => 'completed', 'games_a' => 10, 'games_b' => 5,  'score_a' => 10, 'score_b' => 5],
            'round_5' => ['status' => 'completed', 'games_a' => 10, 'games_b' => 11, 'score_a' => 10, 'score_b' => 11],
            'round_6' => ['status' => 'completed', 'games_a' => 10, 'games_b' => 5,  'score_a' => 10, 'score_b' => 5],
        ];

        $recap = ScoringService::calculateRecap($game, $sessionScores);
        $players = collect($recap)->keyBy('name');

        $this->assertEquals(1, $players['Player A']['rank']);
        $this->assertEquals(32, $players['Player A']['points_for']);

        $this->assertEquals(2, $players['Player B']['rank']);
        $this->assertEquals(30, $players['Player B']['points_for']);

        $this->assertEquals(3, $players['Player C']['rank']);
        $this->assertEquals(30, $players['Player C']['points_for']);

        $this->assertEquals(4, $players['Player D']['rank']);
    }

    /** RULE 2 & Fixture 2: Competition ranking gives 1, 1, 3, 4 for tied metrics */
    public function test_rule_2_joint_rank_uses_competition_ranking_1_1_3_4(): void
    {
        $game = [
            'match_format' => 'Americano',
            'scoring_system' => 'First to 21',
            'participants' => [
                ['name' => 'Player A'],
                ['name' => 'Player B'],
                ['name' => 'Player C'],
                ['name' => 'Player D'],
            ],
            'drawing' => [
                'round_1' => ['team_a' => ['Player A'], 'team_b' => ['Player C']],
                'round_2' => ['team_a' => ['Player B'], 'team_b' => ['Player D']],
            ],
        ];

        $sessionScores = [
            'round_1' => ['status' => 'completed', 'games_a' => 15, 'games_b' => 10, 'score_a' => 15, 'score_b' => 10],
            'round_2' => ['status' => 'completed', 'games_a' => 15, 'games_b' => 10, 'score_a' => 15, 'score_b' => 10],
        ];

        $recap = ScoringService::calculateRecap($game, $sessionScores);
        $players = collect($recap)->keyBy('name');

        $this->assertEquals(1, $players['Player A']['rank']);
        $this->assertEquals(1, $players['Player B']['rank']);
        $this->assertEquals(3, $players['Player C']['rank']);
        $this->assertEquals(3, $players['Player D']['rank']);
    }

    /** RULE 2: Tiga pemain dengan ranking identik menghasilkan 1, 1, 1, 4 */
    public function test_rule_2_three_players_tied_gives_1_1_1_4(): void
    {
        $game = [
            'match_format' => 'Americano',
            'scoring_system' => 'First to 21',
            'participants' => [
                ['name' => 'Player A'],
                ['name' => 'Player B'],
                ['name' => 'Player C'],
                ['name' => 'Player D1'],
                ['name' => 'Player D2'],
                ['name' => 'Player D3'],
            ],
            'drawing' => [
                'round_1' => ['team_a' => ['Player A'], 'team_b' => ['Player D1']],
                'round_2' => ['team_a' => ['Player B'], 'team_b' => ['Player D2']],
                'round_3' => ['team_a' => ['Player C'], 'team_b' => ['Player D3']],
            ],
        ];

        // A, B, and C each get identical 10-2 win over separate opponents
        $sessionScores = [
            'round_1' => ['status' => 'completed', 'games_a' => 10, 'games_b' => 2, 'score_a' => 10, 'score_b' => 2],
            'round_2' => ['status' => 'completed', 'games_a' => 10, 'games_b' => 2, 'score_a' => 10, 'score_b' => 2],
            'round_3' => ['status' => 'completed', 'games_a' => 10, 'games_b' => 2, 'score_a' => 10, 'score_b' => 2],
        ];

        $recap = ScoringService::calculateRecap($game, $sessionScores);
        $players = collect($recap)->keyBy('name');

        $this->assertEquals(1, $players['Player A']['rank']);
        $this->assertEquals(1, $players['Player B']['rank']);
        $this->assertEquals(1, $players['Player C']['rank']);
        $this->assertEquals(4, $players['Player D1']['rank'], 'Player after three joint 1st must be Rank 4');
        $this->assertEquals(4, $players['Player D2']['rank']);
        $this->assertEquals(4, $players['Player D3']['rank']);
    }

    /** RULE 2 & Fixture 4: Zero state returns empty ranking and hasCompetitiveResults false */
    public function test_rule_2_zero_state_when_no_competitive_match_results(): void
    {
        $game = [
            'match_format' => 'Americano',
            'scoring_system' => 'First to 21',
            'participants' => [
                ['name' => 'Player 1'],
                ['name' => 'Player 2'],
            ],
            'drawing' => [
                'round_1' => ['team_a' => ['Player 1'], 'team_b' => ['Player 2']],
            ],
        ];

        $recap = ScoringService::calculateRecap($game, []);

        foreach ($recap as $rp) {
            $this->assertEquals(0, $rp['rank']);
            $this->assertEquals('Belum Ada Hasil', $rp['medal']['label']);
        }
    }

    /** RULE 3 & Fixture 3: Stopped match retains partial points without false winner */
    public function test_rule_3_stopped_match_counts_partial_points_with_no_false_winner(): void
    {
        $game = [
            'match_format' => 'Americano',
            'scoring_system' => 'First to 21',
            'participants' => [
                ['name' => 'Player A'],
                ['name' => 'Player B'],
            ],
            'drawing' => [
                'round_1' => ['team_a' => ['Player A'], 'team_b' => ['Player B']],
            ],
        ];

        $sessionScores = [
            'round_1' => [
                'status' => 'stopped',
                'games_a' => 5,
                'games_b' => 3,
                'score_a' => 5,
                'score_b' => 3,
                'winner_side' => null,
            ],
        ];

        $recap = ScoringService::calculateRecap($game, $sessionScores);
        $players = collect($recap)->keyBy('name');

        $this->assertEquals(5, $players['Player A']['games_won']);
        $this->assertEquals(2, $players['Player A']['game_diff']);
        $this->assertEquals(0, $players['Player A']['wins']);

        $this->assertEquals(3, $players['Player B']['games_won']);
        $this->assertEquals(-2, $players['Player B']['game_diff']);
        $this->assertEquals(0, $players['Player B']['wins']);
    }

    /** Kombinasi: Completed + Stopped match */
    public function test_combination_completed_and_stopped_matches(): void
    {
        $game = [
            'match_format' => 'Americano',
            'scoring_system' => 'First to 21',
            'participants' => [
                ['name' => 'Player A'],
                ['name' => 'Player B'],
                ['name' => 'Player C'],
                ['name' => 'Player D'],
            ],
            'drawing' => [
                'round_1' => ['team_a' => ['Player A'], 'team_b' => ['Player B']],
                'round_2' => ['team_a' => ['Player C'], 'team_b' => ['Player D']],
            ],
        ];

        $sessionScores = [
            'round_1' => ['status' => 'completed', 'games_a' => 11, 'games_b' => 9, 'score_a' => 11, 'score_b' => 9],
            'round_2' => ['status' => 'stopped', 'games_a' => 4, 'games_b' => 2, 'score_a' => 4, 'score_b' => 2, 'winner_side' => null],
        ];

        $recap = ScoringService::calculateRecap($game, $sessionScores);
        $players = collect($recap)->keyBy('name');

        // A has completed win: 11 pts, 1 win
        $this->assertEquals(11, $players['Player A']['points_for']);
        $this->assertEquals(1, $players['Player A']['wins']);

        // C has stopped match: 4 pts, 0 win
        $this->assertEquals(4, $players['Player C']['points_for']);
        $this->assertEquals(0, $players['Player C']['wins']);
    }

    /** Kombinasi: Multiple Court */
    public function test_combination_multiple_courts(): void
    {
        $game = [
            'match_format' => 'Americano',
            'scoring_system' => 'First to 21',
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
            'round_1_court_1' => ['status' => 'completed', 'games_a' => 10, 'games_b' => 5, 'score_a' => 10, 'score_b' => 5],
            'round_1_court_2' => ['status' => 'completed', 'games_a' => 8, 'games_b' => 6, 'score_a' => 8, 'score_b' => 6],
        ];

        $recap = ScoringService::calculateRecap($game, $sessionScores);
        $players = collect($recap)->keyBy('name');

        $this->assertEquals(10, $players['Player 1']['points_for']);
        $this->assertEquals(5, $players['Player 2']['points_for']);
        $this->assertEquals(8, $players['Player 3']['points_for']);
        $this->assertEquals(6, $players['Player 4']['points_for']);
    }
}
