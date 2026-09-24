<?php

namespace Tests\Unit;

use App\Http\Controllers\Game\GameController;
use App\Http\Controllers\Player\PlayerController;
use App\Models\Player;
use App\Models\User;
use App\Services\Scoring\ScoringService;
use Illuminate\Http\Request;
use Tests\TestCase;

class MemberBugFixesTest extends TestCase
{
    public function test_bug_mem_003_empty_state_for_player_with_no_matches()
    {
        $user = new User([
            'nama' => 'Pemain Baru',
            'email' => 'newplayer@matcha.test',
            'role' => 'member',
        ]);
        $user->user_id = 999999;

        $player = new Player([
            'user_id' => 999999,
            'nama' => 'Pemain Baru',
            'email' => 'newplayer@matcha.test',
            'level' => 'Beginner',
        ]);
        $player->player_id = 999999;
        $player->setRelation('user', $user);

        $recap = PlayerController::calculateRealPlayerRecap($user, $player);

        $this->assertFalse($recap['has_matches']);
        $this->assertEquals(0, $recap['player']['total_matches']);
        $this->assertEquals(0, $recap['player']['wins']);
        $this->assertEquals(0, $recap['player']['losses']);
        $this->assertEquals('0%', $recap['player']['win_rate']);
        $this->assertEmpty($recap['recent_matches']);
    }

    public function test_bug_mem_006_007_008_leaderboard_ranking_and_tie_breaking()
    {
        $game = [
            'id' => 101,
            'scoring_system' => 'First to 8',
            'participants' => [
                ['name' => 'Budi', 'avatar' => null, 'level' => 'Intermediate', 'is_member' => true],
                ['name' => 'Andi', 'avatar' => null, 'level' => 'Intermediate', 'is_member' => true],
                ['name' => 'Citra', 'avatar' => null, 'level' => 'Intermediate', 'is_member' => true],
                ['name' => 'Dewi', 'avatar' => null, 'level' => 'Intermediate', 'is_member' => true],
            ],
            'drawing' => [
                'round_1' => [
                    'matches' => [
                        [
                            'court' => 1,
                            'team_a_names' => ['Andi', 'Budi'],
                            'team_b_names' => ['Citra', 'Dewi'],
                        ],
                    ],
                ],
            ],
        ];

        // Andi & Budi win 8 - 4 against Citra & Dewi
        $sessionScores = [
            'round_1_court_1' => [
                'status' => 'completed',
                'score_a' => 8,
                'score_b' => 4,
                'games_a' => 8,
                'games_b' => 4,
            ],
        ];

        $ranked = ScoringService::calculateRecap($game, $sessionScores);

        // Andi and Budi both have 8 points, +4 point_diff, 1 win
        // Tie-break should order alphabetically: Andi (Rank 1), Budi (Rank 2)
        $this->assertEquals('Andi', $ranked[0]['name']);
        $this->assertEquals(1, $ranked[0]['rank']);
        $this->assertEquals(8, $ranked[0]['points_for']);
        $this->assertEquals(4, $ranked[0]['point_diff']);
        $this->assertEquals(1, $ranked[0]['wins']);

        $this->assertEquals('Budi', $ranked[1]['name']);
        $this->assertEquals(2, $ranked[1]['rank']);
        $this->assertEquals(8, $ranked[1]['points_for']);

        // Citra and Dewi have 4 points, -4 point_diff, 0 wins
        // Tie-break: Citra (Rank 3), Dewi (Rank 4)
        $this->assertEquals('Citra', $ranked[2]['name']);
        $this->assertEquals(3, $ranked[2]['rank']);
        $this->assertEquals('Dewi', $ranked[3]['name']);
        $this->assertEquals(4, $ranked[3]['rank']);
    }

    public function test_bug_mem_001_and_002_join_session_rejects_unauthenticated_and_invalid_id()
    {
        $controller = new GameController;

        // 1. Non-existent session ID should redirect to games.index
        $request = Request::create('/games/999999/join', 'POST');
        $response = $controller->joinSession(999999, $request);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertTrue($response->isRedirect(route('games.index')));

        // 2. BUG-MEM-001: Authenticated request with non-existent session ID should return 404 or error redirect
        $user = new User([
            'nama' => 'Pemain Matcha',
            'email' => 'pemain@matcha.test',
            'role' => 'member',
        ]);
        $user->user_id = 999999;
        $this->actingAs($user);

        $jsonRequest = Request::create('/games/999999/join', 'POST', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $jsonResponse = $controller->joinSession(999999, $jsonRequest);

        $this->assertEquals(404, $jsonResponse->getStatusCode());
        $this->assertStringContainsString('tidak ditemukan', $jsonResponse->getContent());
    }
}
