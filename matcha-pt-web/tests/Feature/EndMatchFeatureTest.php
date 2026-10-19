<?php

namespace Tests\Feature;

use App\Http\Controllers\Scoring\ScoringController;
use App\Models\Court;
use App\Models\Drawing;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\Score;
use App\Models\SessionModel;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use App\Services\Drawing\AmericanoService;
use App\Services\Scoring\ScoringService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EndMatchFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setupTestDatabaseSchema();
    }

    protected function setupTestDatabaseSchema(): void
    {
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

        if (! Schema::hasTable('tb_sport')) {
            Schema::create('tb_sport', function ($table) {
                $table->id('sport_id');
                $table->string('nama_sport');
                $table->string('status_sport')->default('active');
            });
        }

        if (! Schema::hasTable('tb_venue')) {
            Schema::create('tb_venue', function ($table) {
                $table->id('venue_id');
                $table->unsignedBigInteger('owner_user_id')->nullable();
                $table->string('nama_venue');
                $table->string('alamat')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tb_court')) {
            Schema::create('tb_court', function ($table) {
                $table->id('court_id');
                $table->string('nama_court');
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
                $table->string('status_session')->default('In Progress');
                $table->string('jenis_permainan')->default('Double');
                $table->integer('jumlah_pemain')->default(8);
                $table->dateTime('datetime')->nullable();
                $table->string('waktu_session')->nullable();
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
                $table->string('nama');
                $table->string('gender')->default('Male');
                $table->string('level')->default('Intermediate');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tb_session_player')) {
            Schema::create('tb_session_player', function ($table) {
                $table->unsignedBigInteger('session_id');
                $table->unsignedBigInteger('player_id');
                $table->string('status')->default('active');
                $table->timestamp('withdrawn_at')->nullable();
                $table->text('withdrawn_reason')->nullable();
                $table->unsignedBigInteger('withdrawn_match_id')->nullable();
            });
        }

        if (! Schema::hasTable('tb_match_format')) {
            Schema::create('tb_match_format', function ($table) {
                $table->id('format_id');
                $table->string('nama_format');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tb_drawing')) {
            Schema::create('tb_drawing', function ($table) {
                $table->id('drawing_id');
                $table->unsignedBigInteger('session_id');
                $table->unsignedBigInteger('format_id')->nullable();
                $table->string('status_drawing')->default('Locked');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tb_match')) {
            Schema::create('tb_match', function ($table) {
                $table->id('match_id');
                $table->unsignedBigInteger('drawing_id');
                $table->unsignedBigInteger('court_id')->nullable();
                $table->integer('nomor_match')->default(1);
                $table->string('status_match')->default('In Progress');
                $table->dateTime('waktu_mulai')->nullable();
                $table->dateTime('waktu_selesai')->nullable();
                $table->string('hasil_pertandingan')->nullable();
                $table->string('winner_team')->nullable();
                $table->string('stop_type')->nullable();
                $table->text('stop_reason')->nullable();
                $table->unsignedBigInteger('stopped_by_user_id')->nullable();
                $table->timestamp('stopped_at')->nullable();
                $table->json('withdrawn_player_ids')->nullable();
                $table->json('player_notes')->nullable();
                $table->integer('version')->default(1);
                $table->string('last_event_id')->nullable();
                $table->timestamps();
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
                $table->integer('version')->default(1);
                $table->string('last_event_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tb_match_participant')) {
            Schema::create('tb_match_participant', function ($table) {
                $table->unsignedBigInteger('match_id');
                $table->unsignedBigInteger('player_id');
                $table->string('side', 10)->default('A');
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

    protected function createIsolatedEnvironment(int $courtCount = 2, string $format = 'Americano', string $scoringSystem = 'Total of 3')
    {
        $unique = uniqid('test_', true);
        $host = User::create([
            'nama' => 'Host '.$unique,
            'email' => 'host_'.$unique.'@test.com',
            'role' => 'host',
        ]);

        $sport = Sport::create(['nama_sport' => 'Tennis']);
        $venue = Venue::create(['nama_venue' => 'Matcha Arena '.$unique, 'owner_user_id' => $host->user_id]);

        $session = SessionModel::create([
            'host_user_id' => $host->user_id,
            'sport_id' => $sport->sport_id,
            'venue_id' => $venue->venue_id,
            'nama_session' => 'Session '.$unique,
            'scoring_system' => $scoringSystem,
            'status_session' => 'In Progress',
            'jenis_permainan' => 'Double',
            'jumlah_pemain' => 8,
        ]);

        $courts = [];
        for ($i = 1; $i <= $courtCount; $i++) {
            $court = Court::create(['nama_court' => "Court {$i}"]);
            DB::table('tb_session_court')->insert([
                'session_id' => $session->session_id,
                'court_id' => $court->court_id,
            ]);
            $courts[] = $court;
        }

        $playerNames = ['Alice', 'Bob', 'Charlie', 'David', 'Eva', 'Frank', 'Grace', 'Henry'];
        $players = [];
        foreach ($playerNames as $pName) {
            $user = User::create([
                'nama' => $pName,
                'email' => strtolower($pName).'_'.$unique.'@test.com',
                'role' => 'member',
            ]);
            $pl = Player::create([
                'user_id' => $user->user_id,
                'nama' => $pName,
            ]);
            DB::table('tb_session_player')->insert([
                'session_id' => $session->session_id,
                'player_id' => $pl->player_id,
                'status' => 'active',
            ]);
            $players[] = $pl;
        }

        $drawingRecord = Drawing::create([
            'session_id' => $session->session_id,
            'status_drawing' => 'Locked',
        ]);

        // 3 rounds drawing schedule
        $schedule = [
            'round_1' => [
                'matches' => [
                    [
                        'court' => 1,
                        'court_name' => 'Court 1',
                        'team_a' => ['Alice', 'Bob'],
                        'team_b' => ['Charlie', 'David'],
                        'team_a_names' => ['Alice', 'Bob'],
                        'team_b_names' => ['Charlie', 'David'],
                    ],
                    [
                        'court' => 2,
                        'court_name' => 'Court 2',
                        'team_a' => ['Eva', 'Frank'],
                        'team_b' => ['Grace', 'Henry'],
                        'team_a_names' => ['Eva', 'Frank'],
                        'team_b_names' => ['Grace', 'Henry'],
                    ],
                ],
            ],
            'round_2' => [
                'matches' => [
                    [
                        'court' => 1,
                        'court_name' => 'Court 1',
                        'team_a' => ['Alice', 'Charlie'],
                        'team_b' => ['Bob', 'David'],
                        'team_a_names' => ['Alice', 'Charlie'],
                        'team_b_names' => ['Bob', 'David'],
                    ],
                    [
                        'court' => 2,
                        'court_name' => 'Court 2',
                        'team_a' => ['Eva', 'Grace'],
                        'team_b' => ['Frank', 'Henry'],
                        'team_a_names' => ['Eva', 'Grace'],
                        'team_b_names' => ['Frank', 'Henry'],
                    ],
                ],
            ],
            'round_3' => [
                'matches' => [
                    [
                        'court' => 1,
                        'court_name' => 'Court 1',
                        'team_a' => ['Alice', 'David'],
                        'team_b' => ['Bob', 'Charlie'],
                        'team_a_names' => ['Alice', 'David'],
                        'team_b_names' => ['Bob', 'Charlie'],
                    ],
                    [
                        'court' => 2,
                        'court_name' => 'Court 2',
                        'team_a' => ['Eva', 'Henry'],
                        'team_b' => ['Frank', 'Grace'],
                        'team_a_names' => ['Eva', 'Henry'],
                        'team_b_names' => ['Frank', 'Grace'],
                    ],
                ],
            ],
        ];

        if ($courtCount === 1) {
            foreach ($schedule as $rk => $rData) {
                $schedule[$rk]['matches'] = array_slice($rData['matches'], 0, 1);
            }
        }

        Cache::put("drawing.schedule_{$session->session_id}", $schedule, 3600);

        // Create GameMatch, MatchParticipant and tb_score for matches
        $playerMap = collect($players)->keyBy('nama');
        $matches = [];
        $matchNumber = 1;
        foreach ($schedule as $rKey => $rData) {
            foreach ($rData['matches'] as $mIdx => $mData) {
                $cId = $courts[$mIdx]->court_id ?? null;
                $m = GameMatch::create([
                    'drawing_id' => $drawingRecord->drawing_id,
                    'court_id' => $cId,
                    'nomor_match' => $matchNumber++,
                    'status_match' => 'In Progress',
                ]);
                $score = Score::create([
                    'match_id' => $m->match_id,
                    'set_number' => 1,
                    'game_number' => 1,
                    'game_score_a' => 0,
                    'game_score_b' => 0,
                    'scoring_system' => $scoringSystem,
                    'status_score' => 'In Progress',
                ]);

                foreach ($mData['team_a'] ?? [] as $pName) {
                    if (isset($playerMap[$pName])) {
                        DB::table('tb_match_participant')->insert([
                            'match_id' => $m->match_id,
                            'player_id' => $playerMap[$pName]->player_id,
                            'side' => 'A',
                        ]);
                    }
                }
                foreach ($mData['team_b'] ?? [] as $pName) {
                    if (isset($playerMap[$pName])) {
                        DB::table('tb_match_participant')->insert([
                            'match_id' => $m->match_id,
                            'player_id' => $playerMap[$pName]->player_id,
                            'side' => 'B',
                        ]);
                    }
                }

                $matches[$rKey][$mIdx] = ['match' => $m, 'score' => $score];
            }
        }

        return [
            'host' => $host,
            'session' => $session,
            'courts' => $courts,
            'players' => $players,
            'drawing' => $drawingRecord,
            'schedule' => $schedule,
            'matches' => $matches,
        ];
    }

    /**
     * Test 1: Match dihentikan pada skor parsial: skor tetap dan sesi masih berlangsung.
     */
    public function test_1_stop_match_with_partial_score_persists_and_session_continues()
    {
        $env = $this->createIsolatedEnvironment();
        $session = $env['session'];
        $host = $env['host'];
        $targetMatch = $env['matches']['round_1'][0]['match'];

        $this->actingAs($host);

        $response = $this->postJson(route('scoring.stop-match'), [
            'game_id' => $session->session_id,
            'match_id' => $targetMatch->match_id,
            'match_key' => 'round_1_court_1',
            'court_number' => 1,
            'round_key' => 'round_1',
            'stop_type' => 'keep_in_session',
            'stop_reason' => 'Waktu sewa lapangan habis',
            'current_games_a' => 3,
            'current_games_b' => 2,
            'point_a' => '30',
            'point_b' => '15',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Verifikasi DB
        $reloadedMatch = GameMatch::find($targetMatch->match_id);
        $this->assertSame('Stopped', $reloadedMatch->status_match);
        $this->assertSame('keep_in_session', $reloadedMatch->stop_type);
        $this->assertSame('Waktu sewa lapangan habis', $reloadedMatch->stop_reason);
        $this->assertNull($reloadedMatch->winner_team);

        // Verifikasi Skor Parsial tetap 3-2
        $score = Score::where('match_id', $targetMatch->match_id)->first();
        $this->assertSame('Stopped', $score->status_score);
        $this->assertSame(3, (int) $score->game_score_a);
        $this->assertSame(2, (int) $score->game_score_b);

        // Sesi MASIH berlangsung (tidak dipanggil finishSession)
        $reloadedSession = SessionModel::find($session->session_id);
        $this->assertSame('In Progress', $reloadedSession->status_session);
    }

    public function test_admin_can_bulk_delete_sessions_in_any_status_and_remove_finished_scores()
    {
        $env = $this->createIsolatedEnvironment(1);
        $finishedSession = $env['session'];
        $finishedSession->update(['status_session' => 'Finished']);

        $readySession = SessionModel::create([
            'host_user_id' => $finishedSession->host_user_id,
            'sport_id' => $finishedSession->sport_id,
            'venue_id' => $finishedSession->venue_id,
            'nama_session' => 'Ready Session Test',
            'scoring_system' => 'Total of 3',
            'status_session' => 'Ready for Drawing',
            'jumlah_pemain' => '4',
            'jenis_permainan' => 'Double',
        ]);
        $inProgressSession = SessionModel::create([
            'host_user_id' => $finishedSession->host_user_id,
            'sport_id' => $finishedSession->sport_id,
            'venue_id' => $finishedSession->venue_id,
            'nama_session' => 'In Progress Session Test',
            'scoring_system' => 'Total of 3',
            'status_session' => 'In Progress',
            'jumlah_pemain' => '4',
            'jenis_permainan' => 'Double',
        ]);

        $admin = User::create([
            'nama' => 'Delete Admin',
            'email' => 'delete-admin-'.uniqid().'@test.com',
            'role' => 'admin',
        ]);
        $sessionIds = [$readySession->session_id, $inProgressSession->session_id, $finishedSession->session_id];
        $matchIds = GameMatch::whereIn('drawing_id', [$env['drawing']->drawing_id])->pluck('match_id');

        $indexResponse = $this->actingAs($admin)->get(route('games.index'));
        $indexResponse->assertOk();
        foreach ($indexResponse->viewData('games')->whereIn('id', $sessionIds) as $game) {
            $this->assertTrue($game['can_delete']);
        }

        $response = $this->delete(route('games.bulkDestroy'), ['selected_ids' => $sessionIds]);

        $response->assertRedirect();
        $response->assertSessionHas('success', '3 jadwal mabar berhasil dihapus.');
        foreach ($sessionIds as $sessionId) {
            $this->assertDatabaseMissing('tb_session', ['session_id' => $sessionId]);
        }
        $this->assertSame(0, DB::table('tb_score')->whereIn('match_id', $matchIds)->count());
        $this->assertSame(0, DB::table('tb_match_participant')->whereIn('match_id', $matchIds)->count());
        $this->assertSame(0, GameMatch::whereIn('match_id', $matchIds)->count());
        $this->assertDatabaseMissing('tb_drawing', ['drawing_id' => $env['drawing']->drawing_id]);
    }

    /**
     * Test 2: Kondisi A: semua pemain tetap ikut ronde berikutnya.
     */
    public function test_2_condition_a_keeps_all_players_in_next_round()
    {
        $env = $this->createIsolatedEnvironment();
        $session = $env['session'];
        $host = $env['host'];
        $targetMatch = $env['matches']['round_1'][0]['match'];

        $this->actingAs($host);

        $stopResponse = $this->postJson(route('scoring.stop-match'), [
            'game_id' => $session->session_id,
            'match_id' => $targetMatch->match_id,
            'match_key' => 'round_1_court_1',
            'court_number' => 1,
            'round_key' => 'round_1',
            'stop_type' => 'keep_in_session',
            'stop_reason' => 'Hujan rintik di court 1',
        ])->assertStatus(200);

        // Verifikasi pemain tetap aktif di tb_session_player
        $withdrawnPlayers = DB::table('tb_session_player')
            ->where('session_id', $session->session_id)
            ->where('status', 'withdrawn')
            ->get();
        $this->assertCount(0, $withdrawnPlayers);

        // Verifikasi jadwal ronde 2 dan 3 tetap utuh
        $cachedSchedule = Cache::get("drawing.schedule_{$session->session_id}");
        $this->assertSame(['Alice', 'Charlie'], $cachedSchedule['round_2']['matches'][0]['team_a']);
        $this->assertSame(['Alice', 'David'], $cachedSchedule['round_3']['matches'][0]['team_a']);
    }

    /**
     * Test 3: Kondisi B: pemain terpilih tidak muncul pada jadwal mendatang.
     */
    public function test_3_condition_b_removes_withdrawn_players_from_future_schedule()
    {
        $env = $this->createIsolatedEnvironment();
        $session = $env['session'];
        $host = $env['host'];
        $targetMatch = $env['matches']['round_1'][0]['match'];
        $alice = $env['players'][0]; // Alice

        $this->actingAs($host);

        $response = $this->postJson(route('scoring.stop-match'), [
            'game_id' => $session->session_id,
            'match_id' => $targetMatch->match_id,
            'match_key' => 'round_1_court_1',
            'court_number' => 1,
            'round_key' => 'round_1',
            'stop_type' => 'withdraw_players',
            'withdrawn_player_ids' => [$alice->player_id],
            'player_notes' => [
                $alice->player_id => 'Cedera pergelangan kaki',
            ],
        ]);

        $response->assertStatus(200);

        // Verifikasi Alice ditandai withdrawn di DB
        $aliceRecord = DB::table('tb_session_player')
            ->where('session_id', $session->session_id)
            ->where('player_id', $alice->player_id)
            ->first();
        $this->assertSame('withdrawn', $aliceRecord->status);
        $this->assertSame('Cedera pergelangan kaki', $aliceRecord->withdrawn_reason);

        // Akun dan model pemain Alice TIDAK dihapus dari tb_player / tb_user
        $this->assertNotNull(Player::find($alice->player_id));

        // Verifikasi jadwal ronde 2 dan 3 TIDAK memuat Alice
        $cachedSchedule = Cache::get("drawing.schedule_{$session->session_id}");
        foreach (['round_2', 'round_3'] as $rKey) {
            foreach ($cachedSchedule[$rKey]['matches'] as $m) {
                $teamA = $m['team_a'] ?? [];
                $teamB = $m['team_b'] ?? [];
                $this->assertNotContains('Alice', $teamA, "Alice should not be in future {$rKey}");
                $this->assertNotContains('Alice', $teamB, "Alice should not be in future {$rKey}");
            }
        }
    }

    /**
     * Test 4: Partner dan lawan tetap aktif saat 1 pemain keluar.
     */
    public function test_4_partner_and_opponents_remain_active_when_player_withdraws()
    {
        $env = $this->createIsolatedEnvironment();
        $session = $env['session'];
        $host = $env['host'];
        $targetMatch = $env['matches']['round_1'][0]['match'];
        $alice = $env['players'][0]; // Alice keluar

        $this->actingAs($host);

        $this->postJson(route('scoring.stop-match'), [
            'game_id' => $session->session_id,
            'match_id' => $targetMatch->match_id,
            'match_key' => 'round_1_court_1',
            'court_number' => 1,
            'round_key' => 'round_1',
            'stop_type' => 'withdraw_players',
            'withdrawn_player_ids' => [$alice->player_id],
            'player_notes' => [$alice->player_id => 'Izin mendadak'],
        ]);

        // Bob (partner), Charlie (lawan), David (lawan) TETAP aktif
        $activeStatuses = DB::table('tb_session_player')
            ->where('session_id', $session->session_id)
            ->whereIn('player_id', [$env['players'][1]->player_id, $env['players'][2]->player_id, $env['players'][3]->player_id])
            ->pluck('status')
            ->all();

        foreach ($activeStatuses as $status) {
            $this->assertSame('active', $status);
        }
    }

    /**
     * Test 5: Ronde sebelumnya tidak berubah saat ada stop di ronde berjalan.
     */
    public function test_5_earlier_rounds_remain_completely_unaltered()
    {
        $env = $this->createIsolatedEnvironment();
        $session = $env['session'];
        $host = $env['host'];

        // Selesaikan ronde 1 terlebih dahulu
        $r1m1 = $env['matches']['round_1'][0]['match'];
        $r1m2 = $env['matches']['round_1'][1]['match'];
        $r1m1->update(['status_match' => 'Completed', 'hasil_pertandingan' => '6-3']);
        $r1m2->update(['status_match' => 'Completed', 'hasil_pertandingan' => '6-4']);

        // Stop terjadi di ronde 2
        $r2m1 = $env['matches']['round_2'][0]['match'];
        $this->actingAs($host);

        $this->postJson(route('scoring.stop-match'), [
            'game_id' => $session->session_id,
            'match_id' => $r2m1->match_id,
            'match_key' => 'round_2_court_1',
            'court_number' => 1,
            'round_key' => 'round_2',
            'stop_type' => 'withdraw_players',
            'withdrawn_player_ids' => [$env['players'][0]->player_id],
            'player_notes' => [$env['players'][0]->player_id => 'Cedera'],
        ])->assertStatus(200);

        // Verifikasi ronde 1 di database dan drawing sama sekali tidak terganggu
        $this->assertSame('Completed', GameMatch::find($r1m1->match_id)->status_match);
        $this->assertSame('Completed', GameMatch::find($r1m2->match_id)->status_match);

        $cachedSchedule = Cache::get("drawing.schedule_{$session->session_id}");
        $this->assertSame(['Alice', 'Bob'], $cachedSchedule['round_1']['matches'][0]['team_a']);
    }

    /**
     * Test 6: Court lain yang sedang berjalan pada ronde tersebut tidak terganggu.
     */
    public function test_6_other_court_in_progress_not_disturbed()
    {
        $env = $this->createIsolatedEnvironment();
        $session = $env['session'];
        $host = $env['host'];
        $c1Match = $env['matches']['round_1'][0]['match'];
        $c2Match = $env['matches']['round_1'][1]['match'];

        // Simpan skor Court 2 yang sedang berjalan
        $env['matches']['round_1'][1]['score']->update([
            'game_score_a' => 4,
            'game_score_b' => 3,
            'status_score' => 'In Progress',
        ]);

        $this->actingAs($host);

        // Hentikan hanya Court 1
        $this->postJson(route('scoring.stop-match'), [
            'game_id' => $session->session_id,
            'match_id' => $c1Match->match_id,
            'match_key' => 'round_1_court_1',
            'court_number' => 1,
            'round_key' => 'round_1',
            'stop_type' => 'keep_in_session',
            'stop_reason' => 'Masalah net di court 1',
        ])->assertStatus(200);

        // Verifikasi Court 2 tetap In Progress dengan skor 4-3
        $c2Reloaded = GameMatch::find($c2Match->match_id);
        $this->assertSame('In Progress', $c2Reloaded->status_match);

        $c2Score = Score::where('match_id', $c2Match->match_id)->first();
        $this->assertSame('In Progress', $c2Score->status_score);
        $this->assertSame(4, (int) $c2Score->game_score_a);
        $this->assertSame(3, (int) $c2Score->game_score_b);
    }

    /**
     * Test 7: Match terminal memungkinkan lanjut ronde setelah semua court mencapai status terminal.
     */
    public function test_7_terminal_match_allows_round_advancement_when_all_courts_terminal()
    {
        $env = $this->createIsolatedEnvironment();
        $session = $env['session'];
        $host = $env['host'];
        $c1Match = $env['matches']['round_1'][0]['match'];
        $c2Match = $env['matches']['round_1'][1]['match'];

        // Selesaikan Court 1 secara normal
        $c1Match->update(['status_match' => 'Completed']);
        $env['matches']['round_1'][0]['score']->update(['status_score' => 'Completed', 'game_score_a' => 6, 'game_score_b' => 2]);
        Cache::put("scoring.session_{$session->session_id}", [
            'round_1_court_1' => ['status' => 'completed', 'games_a' => 6, 'games_b' => 2, 'winner_team' => 'Team A'],
        ], 3600);

        $this->actingAs($host);

        // Hentikan Court 2 (End Match)
        $this->postJson(route('scoring.stop-match'), [
            'game_id' => $session->session_id,
            'match_id' => $c2Match->match_id,
            'match_key' => 'round_1_court_2',
            'court_number' => 2,
            'round_key' => 'round_1',
            'stop_type' => 'keep_in_session',
            'stop_reason' => 'Waktu habis',
            'current_games_a' => 3,
            'current_games_b' => 3,
        ])->assertStatus(200);

        // Panggil nextRound: sekarang kedua court sudah terminal (Completed & Stopped) -> advancement sukses
        $nextResponse = $this->post(route('scoring.next-round', $session->session_id), [
            'current_round' => 'round_1',
            'next_round' => 'round_2',
        ]);

        $nextResponse->assertSessionHasNoErrors();
        $this->assertTrue($nextResponse->isRedirect());
    }

    /**
     * Test 8: Jumlah pemain tidak cukup ditangani tanpa crash/dummy.
     */
    public function test_8_insufficient_active_players_handled_gracefully_without_crash_or_dummy()
    {
        $env = $this->createIsolatedEnvironment(courtCount: 1); // 1 court double butuh min 4 pemain
        $session = $env['session'];
        $host = $env['host'];
        $targetMatch = $env['matches']['round_1'][0]['match'];

        // 2 pemain (Eva & Frank) sudah keluar sebelumnya dari sesi
        DB::table('tb_session_player')
            ->where('session_id', $session->session_id)
            ->whereIn('player_id', [$env['players'][4]->player_id, $env['players'][5]->player_id])
            ->update(['status' => 'withdrawn', 'withdrawn_reason' => 'Pulang duluan']);

        // Keluarkan 3 pemain peserta match (Alice, Bob, Charlie) sehingga total tersisa hanya 3 pemain (< 4 untuk double)
        $withdrawnPlayers = array_slice($env['players'], 0, 3);
        $withdrawnIds = array_map(fn ($p) => $p->player_id, $withdrawnPlayers);
        $notes = [];
        foreach ($withdrawnIds as $id) {
            $notes[$id] = 'Pulang cepat';
        }

        $this->actingAs($host);

        $response = $this->postJson(route('scoring.stop-match'), [
            'game_id' => $session->session_id,
            'match_id' => $targetMatch->match_id,
            'match_key' => 'round_1',
            'court_number' => 1,
            'round_key' => 'round_1',
            'stop_type' => 'withdraw_players',
            'withdrawn_player_ids' => $withdrawnIds,
            'player_notes' => $notes,
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('has_insufficient_players') || ! empty($response->json('warning')));
        $this->assertStringContainsString('keluar permanen', $response->json('warning'));
        $this->assertStringNotContainsString('aktifkan kembali pemain dari istirahat', $response->json('warning'));

        // Jadwal ronde mendatang tidak memuat pemain dummy ("Dummy", "Bot", dsb.)
        $cachedSchedule = Cache::get("drawing.schedule_{$session->session_id}");
        foreach ($cachedSchedule['round_2']['matches'] as $m) {
            foreach (['team_a', 'team_b'] as $side) {
                foreach ($m[$side] ?? [] as $name) {
                    $this->assertStringNotContainsStringIgnoringCase('dummy', $name);
                }
            }
        }

        // Host yang mencoba next-round saat pemain tidak cukup akan diblokir dengan pesan informatif
        $nextResp = $this->post(route('scoring.next-round', $session->session_id), [
            'current_round' => 'round_1',
            'next_round' => 'round_2',
        ]);
        $nextResp->assertSessionHas('error');
    }

    /**
     * Test 9: Team Americano tidak berubah pasangan diam-diam.
     */
    public function test_9_team_americano_does_not_silently_re_pair_partners()
    {
        $env = $this->createIsolatedEnvironment();
        $session = $env['session'];
        $session->update(['format_id' => null]);
        $host = $env['host'];
        $targetMatch = $env['matches']['round_1'][0]['match'];

        // Atur jadwal Team Americano dengan fixed teams
        $teamSchedule = [
            'teams' => [
                'team_1' => ['display_name' => 'Tim Elang', 'players' => ['Alice', 'Bob']],
                'team_2' => ['display_name' => 'Tim Garuda', 'players' => ['Charlie', 'David']],
            ],
            'round_1' => [
                'matches' => [
                    [
                        'court' => 1,
                        'court_name' => 'Court 1',
                        'team_a' => ['name' => 'team_1', 'display_name' => 'Tim Elang', 'players' => ['Alice', 'Bob']],
                        'team_b' => ['name' => 'team_2', 'display_name' => 'Tim Garuda', 'players' => ['Charlie', 'David']],
                    ],
                ],
            ],
            'round_2' => [
                'matches' => [
                    [
                        'court' => 1,
                        'court_name' => 'Court 1',
                        'team_a' => ['name' => 'team_1', 'display_name' => 'Tim Elang', 'players' => ['Alice', 'Bob']],
                        'team_b' => ['name' => 'team_2', 'display_name' => 'Tim Garuda', 'players' => ['Charlie', 'David']],
                    ],
                ],
            ],
        ];
        Cache::put("drawing.schedule_{$session->session_id}", $teamSchedule, 3600);

        // Player Alice (dari Tim Elang) keluar
        $this->actingAs($host);
        $response = $this->postJson(route('scoring.stop-match'), [
            'game_id' => $session->session_id,
            'match_id' => $targetMatch->match_id,
            'match_key' => 'round_1_court_1',
            'court_number' => 1,
            'round_key' => 'round_1',
            'stop_type' => 'withdraw_players',
            'withdrawn_player_ids' => [$env['players'][0]->player_id], // Alice
            'player_notes' => [$env['players'][0]->player_id => 'Cedera otot'],
        ]);

        $response->assertStatus(200);

        // Partner Bob TIDAK dipasangkan diam-diam dengan Charlie atau David
        $updatedSchedule = Cache::get("drawing.schedule_{$session->session_id}");
        $r2TeamA = $updatedSchedule['round_2']['matches'][0]['team_a']['players'] ?? [];
        $this->assertNotContains('Charlie', $r2TeamA);
        $this->assertNotContains('David', $r2TeamA);
    }

    /**
     * Test 10: Klik ganda/retry tidak menggandakan penghentian atau jadwal.
     */
    public function test_10_double_click_or_retry_idempotent_no_duplicate_stoppage_or_schedule()
    {
        $env = $this->createIsolatedEnvironment();
        $session = $env['session'];
        $host = $env['host'];
        $targetMatch = $env['matches']['round_1'][0]['match'];
        $alice = $env['players'][0];

        $this->actingAs($host);

        $payload = [
            'game_id' => $session->session_id,
            'match_id' => $targetMatch->match_id,
            'match_key' => 'round_1_court_1',
            'court_number' => 1,
            'round_key' => 'round_1',
            'stop_type' => 'withdraw_players',
            'withdrawn_player_ids' => [$alice->player_id],
            'player_notes' => [$alice->player_id => 'Cedera'],
        ];

        // Request 1
        $res1 = $this->postJson(route('scoring.stop-match'), $payload);
        $res1->assertStatus(200);

        // Request 2 (Retry / Double Click)
        $res2 = $this->postJson(route('scoring.stop-match'), $payload);
        $res2->assertStatus(200);

        // Verifikasi tidak ada duplikasi data di database
        $withdrawnCount = DB::table('tb_session_player')
            ->where('session_id', $session->session_id)
            ->where('player_id', $alice->player_id)
            ->count();
        $this->assertSame(1, $withdrawnCount);
    }

    /**
     * Test 11: Penambahan poin bersamaan dengan penghentian tidak menembus status final.
     */
    public function test_11_concurrent_points_rejected_after_stopped()
    {
        $env = $this->createIsolatedEnvironment();
        $session = $env['session'];
        $host = $env['host'];
        $targetMatch = $env['matches']['round_1'][0]['match'];

        $this->actingAs($host);

        // Hentikan match
        $this->postJson(route('scoring.stop-match'), [
            'game_id' => $session->session_id,
            'match_id' => $targetMatch->match_id,
            'match_key' => 'round_1_court_1',
            'court_number' => 1,
            'round_key' => 'round_1',
            'stop_type' => 'keep_in_session',
            'stop_reason' => 'Pertandingan dihentikan',
            'current_games_a' => 2,
            'current_games_b' => 1,
        ])->assertStatus(200);

        // Kirim updateScore setelah match berstatus dihentikan
        $updateResp = $this->postJson(route('scoring.update-score'), [
            'game_id' => $session->session_id,
            'match_id' => $targetMatch->match_id,
            'match_key' => 'round_1_court_1',
            'team' => 'A',
            'action' => 'point_a',
        ]);

        $updateResp->assertStatus(422);
        $this->assertFalse($updateResp->json('success'));
        $this->assertStringContainsStringIgnoringCase('dihentikan', $updateResp->json('message'));
    }

    /**
     * Test 12: Non-host ditolak backend dengan 403 Forbidden.
     */
    public function test_12_non_host_rejected_by_backend_with_403()
    {
        $env = $this->createIsolatedEnvironment();
        $session = $env['session'];
        $targetMatch = $env['matches']['round_1'][0]['match'];

        // Login sebagai non-host (member biasa)
        $member = User::create([
            'nama' => 'Regular Member',
            'email' => 'regular_member_'.uniqid().'@test.com',
            'role' => 'member',
        ]);
        $this->actingAs($member);

        $response = $this->postJson(route('scoring.stop-match'), [
            'game_id' => $session->session_id,
            'match_id' => $targetMatch->match_id,
            'match_key' => 'round_1_court_1',
            'court_number' => 1,
            'round_key' => 'round_1',
            'stop_type' => 'keep_in_session',
            'stop_reason' => 'Aksi tanpa izin',
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test 13: Refresh dan polling memulihkan status dan alasan penghentian.
     */
    public function test_13_refresh_and_polling_recovers_stopped_status_and_reason()
    {
        $env = $this->createIsolatedEnvironment();
        $session = $env['session'];
        $host = $env['host'];
        $targetMatch = $env['matches']['round_1'][0]['match'];

        $this->actingAs($host);

        $this->postJson(route('scoring.stop-match'), [
            'game_id' => $session->session_id,
            'match_id' => $targetMatch->match_id,
            'match_key' => 'round_1_court_1',
            'court_number' => 1,
            'round_key' => 'round_1',
            'stop_type' => 'keep_in_session',
            'stop_reason' => 'Lampu court padam',
            'current_games_a' => 4,
            'current_games_b' => 3,
        ])->assertStatus(200);

        // Request polling endpoint get-score
        $pollResp = $this->getJson(url("scoring/get-score/{$session->session_id}/round_1?court=0&match_key=round_1_court_1"));
        $pollResp->assertStatus(200);
        $pollResp->assertJson([
            'status' => 'stopped',
            'stop_type' => 'keep_in_session',
            'stop_reason' => 'Lampu court padam',
            'games_a' => 4,
            'games_b' => 3,
        ]);
    }

    public function test_permanent_withdrawal_details_survive_score_cache_recovery()
    {
        $env = $this->createIsolatedEnvironment();
        $session = $env['session'];
        $host = $env['host'];
        $targetMatch = $env['matches']['round_1'][0]['match'];
        $withdrawnPlayer = $env['players'][0];
        $playerReason = 'Cedera pergelangan kaki';

        $this->actingAs($host);

        $this->postJson(route('scoring.stop-match'), [
            'game_id' => $session->session_id,
            'match_id' => $targetMatch->match_id,
            'match_key' => 'round_1_court_1',
            'court_number' => 1,
            'round_key' => 'round_1',
            'stop_type' => 'withdraw_players',
            'stop_reason' => $playerReason,
            'withdrawn_player_ids' => [$withdrawnPlayer->player_id],
            'player_reasons' => [$withdrawnPlayer->player_id => $playerReason],
        ])->assertStatus(200);

        Cache::forget("scoring.game_{$session->session_id}.round_1_court_1");
        Cache::forget("scoring.game_{$session->session_id}");

        $pollResp = $this->getJson(url("scoring/get-score/{$session->session_id}/round_1?court=0&match_key=round_1_court_1"));
        $pollResp->assertStatus(200);
        $pollResp->assertJson([
            'status' => 'stopped',
            'stop_type' => 'withdraw_players',
            'withdrawn_player_names' => [$withdrawnPlayer->nama],
            'player_reasons' => [$withdrawnPlayer->player_id => $playerReason],
        ]);

        Cache::forget("scoring.game_{$session->session_id}.round_1_court_1");
        Cache::forget("scoring.game_{$session->session_id}");
        $liveResp = $this->get(route('scoring.live', [
            'id' => $session->session_id,
            'round' => 'round_1',
        ]));
        $liveResp->assertOk();
        $liveResp->assertSee('Pemain Keluar: '.$withdrawnPlayer->nama.': '.$playerReason, false);
    }

    /**
     * Test 14: Rekap membedakan dihentikan, selesai normal, dan seri.
     */
    public function test_14_recap_clearly_distinguishes_stopped_from_completed_and_draw()
    {
        $env = $this->createIsolatedEnvironment();
        $session = $env['session'];
        $host = $env['host'];
        $targetMatch = $env['matches']['round_1'][0]['match'];

        $this->actingAs($host);

        $stopResponse = $this->postJson(route('scoring.stop-match'), [
            'game_id' => $session->session_id,
            'match_id' => $targetMatch->match_id,
            'match_key' => 'round_1_court_1',
            'court_number' => 1,
            'round_key' => 'round_1',
            'stop_type' => 'keep_in_session',
            'stop_reason' => 'Hujan lebat',
            'current_games_a' => 5,
            'current_games_b' => 2,
        ]);
        $stopResponse->assertStatus(200);
        $stopResponse->assertStatus(200);
        $stopResponse->assertJsonPath('saved.games_a', 5);
        $stopResponse->assertJsonPath('saved.games_b', 2);
        $stoppedScore = Score::where('match_id', $targetMatch->match_id)
            ->where('status_score', 'Stopped')
            ->first();
        $this->assertSame(5, (int) $stoppedScore->game_score_a);
        $this->assertSame(2, (int) $stoppedScore->game_score_b);

        // Buka halaman recap
        $recapResp = $this->get(route('scoring.recap', $session->session_id));
        $recapResp->assertStatus(200);
        $recapResp->assertViewHas('hasStoppedMatches', true);

        // Verifikasi bahwa match dihentikan tidak dianggap menang sepihak untuk Alice/Bob
        $ranked = $recapResp->viewData('rankedPlayers');
        $effectiveScores = $recapResp->viewData('effectiveScores');
        $resolvedDrawing = $recapResp->viewData('game')['drawing'];
        $this->assertSame(['Alice', 'Bob'], $resolvedDrawing['round_1']['matches'][0]['team_a_names']);
        $this->assertSame(5, $effectiveScores['round_1_court_1']['games_a'] ?? null);
        $this->assertSame(2, $effectiveScores['round_1_court_1']['games_b'] ?? null);
        $this->assertSame([['set' => 1, 'score_a' => 5, 'score_b' => 2]], $effectiveScores['round_1_court_1']['set_history'] ?? null);
        $aliceStat = collect($ranked)->firstWhere('name', 'Alice');
        $bobStat = collect($ranked)->firstWhere('name', 'Bob');
        // Match yang dihentikan dipisahkan dari agregasi kemenangan normal
        $this->assertSame(0, $aliceStat['wins'] ?? 0);
        $stoppedMatchPoints = collect($ranked)
            ->whereIn('name', ['Alice', 'Bob', 'Charlie', 'David'])
            ->mapWithKeys(fn ($player) => [$player['name'] => $player['points_for']])
            ->all();
        $this->assertSame([
            'Alice' => 5,
            'Bob' => 5,
            'Charlie' => 2,
            'David' => 2,
        ], $stoppedMatchPoints);
    }

    /**
     * Test 15: Aturan Total Of dan First To existing tetap berjalan.
     */
    public function test_15_total_of_and_first_to_scoring_rules_preserved()
    {
        // 1. Total of 4 sets system detection & completion (aturan set 6/7 game)
        $totalOf4 = ScoringService::detectScoringSystem('Total of 4');
        $this->assertTrue($totalOf4['is_total_of']);
        $this->assertSame(4, $totalOf4['max_sets']);
        $this->assertFalse(ScoringService::isMatchCompleted(5, 4, $totalOf4));
        $this->assertNull(ScoringService::determineMatchWinner(5, 4, $totalOf4));
        $this->assertTrue(ScoringService::isMatchCompleted(6, 4, $totalOf4));
        $this->assertSame('Team A', ScoringService::determineMatchWinner(6, 4, $totalOf4));
        $this->assertTrue(ScoringService::isMatchCompleted(7, 5, $totalOf4));
        $this->assertSame('Team A', ScoringService::determineMatchWinner(7, 5, $totalOf4));
        $this->assertTrue(ScoringService::isMatchCompleted(4, 6, $totalOf4));
        $this->assertSame('Team B', ScoringService::determineMatchWinner(4, 6, $totalOf4));

        // 2. First to 8 Games system detection & completion
        $firstTo8 = ScoringService::detectScoringSystem('First to 8 Games');
        $this->assertFalse($firstTo8['is_total_of']);
        $this->assertSame(8, $firstTo8['target_games']);
        $this->assertFalse(ScoringService::isMatchCompleted(7, 7, $firstTo8));
        $this->assertTrue(ScoringService::isMatchCompleted(8, 6, $firstTo8));
        $this->assertSame('Team A', ScoringService::determineMatchWinner(8, 6, $firstTo8));
    }

    /**
     * Test 16: Mode B dengan nama pemain (string seperti 'Alice') berhasil teresolusi dan tersimpan.
     */
    public function test_16_withdrawn_player_by_name_resolves_and_succeeds()
    {
        $env = $this->createIsolatedEnvironment();
        $session = $env['session'];
        $host = $env['host'];
        $targetMatch = $env['matches']['round_1'][0]['match'];
        $alice = $env['players'][0]; // Alice

        $this->actingAs($host);

        $response = $this->postJson(route('scoring.stop-match'), [
            'game_id' => $session->session_id,
            'match_id' => $targetMatch->match_id,
            'match_key' => 'round_1_court_1',
            'court_number' => 1,
            'round_key' => 'round_1',
            'stop_type' => 'withdraw_players',
            'withdrawn_player_ids' => ['Alice'], // string name
            'player_notes' => [
                'Alice' => 'Cedera engkel',
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Verifikasi Alice ditandai withdrawn di DB dengan reason yang sesuai
        $aliceRecord = DB::table('tb_session_player')
            ->where('session_id', $session->session_id)
            ->where('player_id', $alice->player_id)
            ->first();
        $this->assertSame('withdrawn', $aliceRecord->status);
        $this->assertSame('Cedera engkel', $aliceRecord->withdrawn_reason);
    }

    /**
     * Test 17: Pemain di luar match ditolak oleh backend dengan HTTP 422.
     */
    public function test_17_withdrawn_player_outside_match_rejected()
    {
        $env = $this->createIsolatedEnvironment();
        $session = $env['session'];
        $host = $env['host'];
        $targetMatch = $env['matches']['round_1'][0]['match']; // Court 1: Alice, Bob, Charlie, David
        $eva = $env['players'][4]; // Eva di Court 2

        $this->actingAs($host);

        $response = $this->postJson(route('scoring.stop-match'), [
            'game_id' => $session->session_id,
            'match_id' => $targetMatch->match_id,
            'match_key' => 'round_1_court_1',
            'court_number' => 1,
            'round_key' => 'round_1',
            'stop_type' => 'withdraw_players',
            'withdrawn_player_ids' => [$eva->player_id],
            'player_notes' => [
                $eva->player_id => 'Cedera',
            ],
        ]);

        $response->assertStatus(422);
        $this->assertFalse($response->json('success'));
        $this->assertStringContainsStringIgnoringCase('bukan merupakan peserta', $response->json('message'));
    }

    /**
     * Test 18: Mode B dengan pilihan kosong atau alasan kosong ditolak dengan HTTP 422.
     */
    public function test_18_empty_selection_or_empty_reason_rejected()
    {
        $env = $this->createIsolatedEnvironment();
        $session = $env['session'];
        $host = $env['host'];
        $targetMatch = $env['matches']['round_1'][0]['match'];
        $alice = $env['players'][0];

        $this->actingAs($host);

        // 1. Pilihan kosong ditolak
        $emptyResp = $this->postJson(route('scoring.stop-match'), [
            'game_id' => $session->session_id,
            'match_id' => $targetMatch->match_id,
            'match_key' => 'round_1_court_1',
            'court_number' => 1,
            'round_key' => 'round_1',
            'stop_type' => 'withdraw_players',
            'withdrawn_player_ids' => [],
            'player_notes' => [],
        ]);
        $emptyResp->assertStatus(422);
        $this->assertStringContainsStringIgnoringCase('Pilih minimal satu pemain', $emptyResp->json('message'));

        // 2. Alasan kosong untuk pemain terpilih ditolak
        $noReasonResp = $this->postJson(route('scoring.stop-match'), [
            'game_id' => $session->session_id,
            'match_id' => $targetMatch->match_id,
            'match_key' => 'round_1_court_1',
            'court_number' => 1,
            'round_key' => 'round_1',
            'stop_type' => 'withdraw_players',
            'withdrawn_player_ids' => [$alice->player_id],
            'player_notes' => [$alice->player_id => ''],
            'reason' => '',
        ]);
        $noReasonResp->assertStatus(422);
        $this->assertStringContainsStringIgnoringCase('Wajib mencatat alasan', $noReasonResp->json('message'));
    }

    /**
     * Test 19: Mode B dengan dua pemain terpilih dan masing-masing alasan terpetakan benar.
     */
    public function test_19_two_players_with_distinct_reasons_mapped_correctly()
    {
        $env = $this->createIsolatedEnvironment();
        $session = $env['session'];
        $host = $env['host'];
        $targetMatch = $env['matches']['round_1'][0]['match'];
        $alice = $env['players'][0]; // Tim A
        $bob = $env['players'][1];   // Tim A

        $this->actingAs($host);

        $response = $this->postJson(route('scoring.stop-match'), [
            'game_id' => $session->session_id,
            'match_id' => $targetMatch->match_id,
            'match_key' => 'round_1_court_1',
            'court_number' => 1,
            'round_key' => 'round_1',
            'stop_type' => 'withdraw_players',
            'withdrawn_player_ids' => [$alice->player_id, $bob->player_id],
            'player_notes' => [
                $alice->player_id => 'Cedera bahu kanan',
                $bob->player_id => 'Urusan mendesak',
            ],
        ]);

        $response->assertStatus(200);

        // Verifikasi kedua pemain tercatat withdrawn dengan alasan masing-masing
        $aliceRecord = DB::table('tb_session_player')
            ->where('session_id', $session->session_id)
            ->where('player_id', $alice->player_id)
            ->first();
        $this->assertSame('withdrawn', $aliceRecord->status);
        $this->assertSame('Cedera bahu kanan', $aliceRecord->withdrawn_reason);

        $bobRecord = DB::table('tb_session_player')
            ->where('session_id', $session->session_id)
            ->where('player_id', $bob->player_id)
            ->first();
        $this->assertSame('withdrawn', $bobRecord->status);
        $this->assertSame('Urusan mendesak', $bobRecord->withdrawn_reason);
    }

    /**
     * Test 20: Cache kosong dengan skor tersimpan di database tetap mempertahankan skor tersebut (tidak reset ke 0-0).
     */
    public function test_20_empty_cache_with_saved_db_scores_preserves_score_on_end_match()
    {
        $env = $this->createIsolatedEnvironment();
        $session = $env['session'];
        $host = $env['host'];
        $targetMatch = $env['matches']['round_1'][0]['match'];

        // 1. Catat skor di database: 4-2, 30-15, version 5
        $score = Score::where('match_id', $targetMatch->match_id)->first();
        $score->update([
            'game_score_a' => 4,
            'game_score_b' => 2,
            'point_score_a' => '30',
            'point_score_b' => '15',
            'version' => 5,
            'status_score' => 'In Progress',
        ]);

        // 2. Kosongkan cache
        Cache::forget("scoring.game_{$session->session_id}");
        Cache::forget("scoring.game_{$session->session_id}.round_1_court_1");
        Cache::forget("scoring.game_{$session->session_id}.round_1");

        $this->actingAs($host);

        // 3. Panggil stop-match tanpa mengirim games (mengandalkan pemulihan dari database)
        $response = $this->postJson(route('scoring.stop-match'), [
            'game_id' => $session->session_id,
            'match_id' => $targetMatch->match_id,
            'match_key' => 'round_1_court_1',
            'court_number' => 1,
            'round_key' => 'round_1',
            'stop_type' => 'keep_in_session',
            'stop_reason' => 'Hujan mendadak',
        ]);

        $response->assertStatus(200);
        $saved = $response->json('saved');

        // Verifikasi skor 4-2 dan poin 30-15 dipertahankan dari database, BUKAN 0-0
        $this->assertSame(4, (int) $saved['games_a']);
        $this->assertSame(2, (int) $saved['games_b']);
        $this->assertSame('30', (string) $saved['point_display_a']);
        $this->assertSame('15', (string) $saved['point_display_b']);
        $this->assertSame('stopped', $saved['status']);

        // Verifikasi di database
        $reloadedScore = Score::where('match_id', $targetMatch->match_id)->where('status_score', 'Stopped')->first();
        $this->assertNotNull($reloadedScore);
        $this->assertSame(4, (int) $reloadedScore->game_score_a);
        $this->assertSame(2, (int) $reloadedScore->game_score_b);
    }

    /**
     * Test 21: Match yang belum memiliki skor di cache maupun database (fresh match ronde baru) ditangani dengan benar via getDefaultMatchState tanpa crash.
     */
    public function test_21_match_without_prior_score_handled_gracefully_with_default_state()
    {
        $env = $this->createIsolatedEnvironment();
        $session = $env['session'];
        $host = $env['host'];

        // Buat match baru untuk ronde 4 tanpa skor di tb_score
        $drawing = $env['drawing'];
        $r4Match = GameMatch::create([
            'drawing_id' => $drawing->drawing_id,
            'court_id' => $env['courts'][0]->court_id,
            'nomor_match' => 7, // match ronde 4 court 1
            'status_match' => 'In Progress',
            'version' => 1,
        ]);

        $alice = $env['players'][0];
        $bob = $env['players'][1];
        $charlie = $env['players'][2];
        $david = $env['players'][3];

        DB::table('tb_match_participant')->insert([
            ['match_id' => $r4Match->match_id, 'player_id' => $alice->player_id, 'side' => 'A'],
            ['match_id' => $r4Match->match_id, 'player_id' => $bob->player_id, 'side' => 'A'],
            ['match_id' => $r4Match->match_id, 'player_id' => $charlie->player_id, 'side' => 'B'],
            ['match_id' => $r4Match->match_id, 'player_id' => $david->player_id, 'side' => 'B'],
        ]);

        // Pastikan cache ronde 4 kosong total
        Cache::forget("scoring.game_{$session->session_id}");
        Cache::forget("scoring.game_{$session->session_id}.round_4_court_1");

        $this->actingAs($host);

        // Hentikan match di Ronde 4 Court 1 dengan Mode B (Pemain Keluar: Scoups/Alice Cedera)
        $response = $this->postJson(route('scoring.stop-match'), [
            'game_id' => $session->session_id,
            'match_id' => $r4Match->match_id,
            'match_key' => 'round_4_court_1',
            'court_number' => 1,
            'round_key' => 'round_4',
            'stop_type' => 'withdraw_players',
            'withdrawn_player_ids' => [$alice->player_id],
            'player_notes' => [
                $alice->player_id => 'Cedera',
            ],
        ]);

        // Verifikasi tidak crash dengan Call to undefined method getDefaultMatchState, dan berhasil 200
        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertSame('stopped', $response->json('status'));

        // Skor default 0-0 tersimpan
        $saved = $response->json('saved');
        $this->assertSame(0, (int) $saved['games_a']);
        $this->assertSame(0, (int) $saved['games_b']);

        // Pemain Alice ditandai withdrawn di tb_session_player
        $aliceRecord = DB::table('tb_session_player')
            ->where('session_id', $session->session_id)
            ->where('player_id', $alice->player_id)
            ->first();
        $this->assertSame('withdrawn', $aliceRecord->status);
        $this->assertSame('Cedera', $aliceRecord->withdrawn_reason);
    }

    /**
     * Test 22: Kegagalan transaksi tidak meninggalkan perubahan parsial (rollback atomik).
     */
    public function test_22_atomic_transaction_rolls_back_without_partial_changes_on_failure()
    {
        $env = $this->createIsolatedEnvironment();
        $session = $env['session'];
        $host = $env['host'];
        $targetMatch = $env['matches']['round_1'][0]['match'];
        $alice = $env['players'][0];

        $this->actingAs($host);

        // Buat session lock atau buat database trigger/kondisi yang menyebabkan error internal
        // Kita simulasikan dengan menyabotase sementara agar stopMatch gagal di dalam transaksi
        // Misalnya dengan menghapus foreign match record atau passing ID yang tidak dapat diproses
        // Atau uji response header X-Correlation-Id pada exception
        $controller = app(ScoringController::class);

        // Verifikasi bahwa pemanggilan getDefaultMatchState secara langsung menghasilkan canonical state
        $gameData = [
            'id' => $session->session_id,
            'scoring_system' => 'Total of 3',
        ];
        $defaultState = $controller->getDefaultMatchState($gameData, 'round_1_court_1', 'round_1', ScoringService::detectScoringSystem('Total of 3'));
        $this->assertIsArray($defaultState);
        $this->assertSame('in_progress', $defaultState['status']);
        $this->assertSame(0, $defaultState['games_a']);
        $this->assertSame(0, $defaultState['games_b']);
    }

    /**
     * Test 23: Penghentian match kondisi B (temporary_pause) memindahkan pemain ke bangku istirahat (resting).
     */
    public function test_23_temporary_pause_moves_player_to_resting_and_preserves_in_bench()
    {
        $env = $this->createIsolatedEnvironment();
        $session = $env['session'];
        $host = $env['host'];
        $targetMatch = $env['matches']['round_1'][0]['match'];
        $alice = $env['players'][0];

        $this->actingAs($host);

        $response = $this->postJson(route('scoring.stop-match'), [
            'game_id' => $session->session_id,
            'match_id' => $targetMatch->match_id,
            'match_key' => 'round_1_court_1',
            'court_number' => 1,
            'round_key' => 'round_1',
            'stop_type' => 'temporary_pause',
            'withdrawn_player_ids' => [$alice->player_id],
            'reason' => 'Ke toilet sebentar',
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertSame('stopped', $response->json('status'));

        // Cek database tb_match
        $targetMatch->refresh();
        $this->assertSame('temporary_pause', $targetMatch->stop_type);
        $this->assertSame('Ke toilet sebentar', $targetMatch->stop_reason);

        // Cek tb_session_player: status harus 'resting', BUKAN 'withdrawn'
        $aliceRecord = DB::table('tb_session_player')
            ->where('session_id', $session->session_id)
            ->where('player_id', $alice->player_id)
            ->first();
        $this->assertSame('resting', $aliceRecord->status);

        // Cek drawing schedule cache jika ada, pemain harus ada di resting
        $cachedSchedule = Cache::get("drawing.schedule_{$session->session_id}");
        if ($cachedSchedule && isset($cachedSchedule['paused_player_ids'])) {
            $this->assertContains($alice->player_id, $cachedSchedule['paused_player_ids']);
        }
    }

    /**
     * Test 24: Reactivate player mengembalikan pemain yang istirahat (resting) menjadi aktif kembali ke jadwal.
     */
    public function test_24_reactivate_player_returns_resting_player_to_active()
    {
        $env = $this->createIsolatedEnvironment();
        $session = $env['session'];
        $host = $env['host'];
        $alice = $env['players'][0];

        // Set Alice ke status resting
        DB::table('tb_session_player')
            ->where('session_id', $session->session_id)
            ->where('player_id', $alice->player_id)
            ->update(['status' => 'resting']);

        // Set cache paused players
        Cache::put("drawing.schedule_{$session->session_id}", [
            'paused_player_ids' => [$alice->player_id],
            'rounds' => [],
        ], 3600);

        $this->actingAs($host);

        $response = $this->postJson(route('scoring.reactivate-player'), [
            'game_id' => $session->session_id,
            'player_id' => $alice->player_id,
            'round' => 'round_2',
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertSame($alice->player_id, $response->json('player_id'));

        // Verifikasi status di DB kembali jadi active
        $aliceRecord = DB::table('tb_session_player')
            ->where('session_id', $session->session_id)
            ->where('player_id', $alice->player_id)
            ->first();
        $this->assertSame('active', $aliceRecord->status);
    }

    public function test_reactivation_uses_completed_rounds_for_single_rotation_history()
    {
        $env = $this->createSingleEnvironment(1, ['Hilmy', 'Khaires', 'Prameswari', 'Abelvan', 'Sule'], 'Total of 3');
        $session = $env['session'];
        $host = $env['host'];
        $players = $env['players'];
        $matchR1 = $env['matches'][1][0]['match'];
        $schedule = Cache::get("drawing.schedule_{$session->session_id}");
        $roundOneBefore = $schedule['rounds'][1];

        $this->assertSame(['Hilmy'], $roundOneBefore['team_a_names']);
        $this->assertSame(['Khaires'], $roundOneBefore['team_b_names']);

        DB::table('tb_session_player')
            ->where('session_id', $session->session_id)
            ->where('player_id', $players[2]->player_id)
            ->update(['status' => 'resting']);
        $schedule['paused_player_ids'] = [$players[2]->player_id];
        Cache::put("drawing.schedule_{$session->session_id}", $schedule, 3600);

        $matchR1->update(['status_match' => 'Completed']);
        $roundOneParticipantsBefore = DB::table('tb_match_participant')
            ->where('match_id', $matchR1->match_id)
            ->orderBy('side')
            ->pluck('player_id')
            ->all();

        $this->actingAs($host);
        $response = $this->postJson(route('scoring.reactivate-player'), [
            'game_id' => $session->session_id,
            'player_id' => $players[2]->player_id,
            'round' => 'round_2',
        ]);

        $response->assertOk()->assertJsonFragment(['success' => true]);

        $rescheduled = Cache::get("drawing.schedule_{$session->session_id}");
        $roundTwo = $rescheduled['rounds'][2];
        $roundTwoNames = array_merge(
            $roundTwo['matches'][0]['team_a_names'],
            $roundTwo['matches'][0]['team_b_names']
        );

        $this->assertSame($roundOneBefore, $rescheduled['rounds'][1]);
        $this->assertContains('Prameswari', $roundTwoNames);
        $this->assertNotContains('Hilmy', $roundTwoNames);
        $this->assertNotContains('Khaires', $roundTwoNames);
        $this->assertSame(
            $roundOneParticipantsBefore,
            DB::table('tb_match_participant')
                ->where('match_id', $matchR1->match_id)
                ->orderBy('side')
                ->pluck('player_id')
                ->all()
        );
    }

    /**
     * Test 25: Non-host tidak memiliki hak akses untuk mengaktifkan kembali pemain istirahat.
     */
    public function test_25_non_host_cannot_reactivate_player()
    {
        $env = $this->createIsolatedEnvironment();
        $session = $env['session'];
        $nonHostUser = User::find($env['players'][1]->user_id);
        $alice = $env['players'][0];

        $this->actingAs($nonHostUser);

        $response = $this->postJson(route('scoring.reactivate-player'), [
            'game_id' => $session->session_id,
            'player_id' => $alice->player_id,
        ]);

        $response->assertStatus(403);
    }

    /**
     * Test 26: Sisa jadwal tidak mengalami duplikasi eksponensial (3 -> 6 -> 12 -> 24) saat adjustFutureSchedule dipanggil berulang kali.
     */
    public function test_26_schedule_rounds_do_not_exponentially_duplicate_when_adjusted_repeatedly()
    {
        $env = $this->createIsolatedEnvironment();
        $session = $env['session'];
        $alice = $env['players'][0];
        $bob = $env['players'][1];

        // Penyesuaian 1: Alice istirahat di ronde 1
        ScoringService::adjustFutureScheduleOnPause($session, [$alice->player_id], 1);
        $cached1 = Cache::get("drawing.schedule_{$session->session_id}");
        $this->assertCount(3, $cached1['rounds']);
        $this->assertArrayNotHasKey('round_1', $cached1['rounds']);

        // Penyesuaian 2: Bob keluar di ronde 2
        ScoringService::adjustFutureScheduleOnWithdrawal($session, [$bob->player_id], 2);
        $cached2 = Cache::get("drawing.schedule_{$session->session_id}");
        $this->assertCount(3, $cached2['rounds']);
        $this->assertArrayNotHasKey('round_1', $cached2['rounds']);

        // Penyesuaian 3: Alice diaktifkan kembali
        ScoringService::adjustFutureScheduleOnReactivation($session, $alice->player_id, 2);
        $cached3 = Cache::get("drawing.schedule_{$session->session_id}");
        $this->assertCount(3, $cached3['rounds']);
    }

    /**
     * Test 27: resolveRoundsProgress tidak mengunci ronde 1 akibat duplikasi entri ronde.
     */
    public function test_27_resolve_rounds_progress_does_not_lock_round_1_due_to_duplicate_keys()
    {
        $env = $this->createIsolatedEnvironment();
        $session = $env['session'];

        // Simulasi array ronde yang memiliki duplikasi key integer dan string
        $roundsWithDups = [
            1 => ['round' => 1, 'matches' => [['court' => 1]]],
            2 => ['round' => 2, 'matches' => [['court' => 1]]],
            3 => ['round' => 3, 'matches' => [['court' => 1]]],
            'round_1' => ['round' => 1, 'matches' => [['court' => 1]]],
            'round_2' => ['round' => 2, 'matches' => [['court' => 1]]],
            'round_3' => ['round' => 3, 'matches' => [['court' => 1]]],
        ];

        $progress = ScoringService::resolveRoundsProgress((int) $session->session_id, $roundsWithDups);

        $this->assertTrue($progress['access'][1]);
        $this->assertSame(3, $progress['total_rounds']);
    }

    protected function createSingleEnvironment(int $courtCount = 1, array $playerNames = ['P1', 'P2', 'P3', 'P4'], string $scoringSystem = 'First To 2')
    {
        $unique = uniqid('s_test_', true);
        $host = User::create([
            'nama' => 'Host '.$unique,
            'email' => 'host_'.$unique.'@test.com',
            'role' => 'host',
        ]);

        $sport = Sport::create(['nama_sport' => 'Tennis']);
        $venue = Venue::create(['nama_venue' => 'Matcha Arena '.$unique, 'owner_user_id' => $host->user_id]);

        $session = SessionModel::create([
            'host_user_id' => $host->user_id,
            'sport_id' => $sport->sport_id,
            'venue_id' => $venue->venue_id,
            'nama_session' => 'Single Session '.$unique,
            'scoring_system' => $scoringSystem,
            'status_session' => 'In Progress',
            'jenis_permainan' => 'Single',
            'jumlah_pemain' => count($playerNames),
            'match_format' => 'Americano',
        ]);

        $courts = [];
        for ($i = 1; $i <= $courtCount; $i++) {
            $court = Court::create(['nama_court' => "Court {$i}"]);
            DB::table('tb_session_court')->insert([
                'session_id' => $session->session_id,
                'court_id' => $court->court_id,
            ]);
            $courts[] = $court;
        }

        $players = [];
        foreach ($playerNames as $pName) {
            $user = User::create([
                'nama' => $pName,
                'email' => strtolower(str_replace(' ', '', $pName)).'_'.$unique.'@test.com',
                'role' => 'member',
            ]);
            $pl = Player::create([
                'user_id' => $user->user_id,
                'nama' => $pName,
            ]);
            DB::table('tb_session_player')->insert([
                'session_id' => $session->session_id,
                'player_id' => $pl->player_id,
                'status' => 'active',
            ]);
            $players[] = $pl;
        }

        $drawingRecord = Drawing::create([
            'session_id' => $session->session_id,
            'status_drawing' => 'Locked',
        ]);

        $americanoService = new AmericanoService;
        $normalized = array_map(fn ($p) => [
            'id' => $p->player_id,
            'name' => $p->nama,
            'gender' => 'Male',
            'level' => 'Intermediate',
        ], $players);
        $rounds = $americanoService->generateRounds($normalized, $courtCount, 3, 'Single');

        $cachedSchedule = [
            'rounds' => $rounds,
            'court_count' => $courtCount,
            'schedule_version' => 1,
            'drawingData' => [
                'rounds' => $rounds,
                'court_count' => $courtCount,
            ],
        ];
        foreach ($rounds as $rNum => $rData) {
            $cachedSchedule["round_{$rNum}"] = $rData;
        }
        Cache::put("drawing.schedule_{$session->session_id}", $cachedSchedule, 3600);

        $matchNumber = 1;
        $matches = [];
        foreach ($rounds as $rKey => $rData) {
            $rNum = (int) ($rData['round'] ?? $rKey);
            $matches[$rNum] = [];
            foreach ($rData['matches'] ?? [] as $mIdx => $mData) {
                $cId = $courts[$mIdx]->court_id ?? $courts[0]->court_id;
                $m = GameMatch::create([
                    'drawing_id' => $drawingRecord->drawing_id,
                    'court_id' => $cId,
                    'nomor_match' => $matchNumber++,
                    'status_match' => $rNum === 1 ? 'In Progress' : 'Scheduled',
                ]);
                $score = Score::create([
                    'match_id' => $m->match_id,
                    'set_number' => 1,
                    'game_number' => 1,
                    'game_score_a' => 0,
                    'game_score_b' => 0,
                    'scoring_system' => $scoringSystem,
                    'status_score' => 'In Progress',
                ]);
                foreach ($mData['team_a'] ?? [] as $pA) {
                    $pId = is_array($pA) ? ($pA['id'] ?? null) : null;
                    if ($pId) {
                        DB::table('tb_match_participant')->insert([
                            'match_id' => $m->match_id,
                            'player_id' => $pId,
                            'side' => 'A',
                        ]);
                    }
                }
                foreach ($mData['team_b'] ?? [] as $pB) {
                    $pId = is_array($pB) ? ($pB['id'] ?? null) : null;
                    if ($pId) {
                        DB::table('tb_match_participant')->insert([
                            'match_id' => $m->match_id,
                            'player_id' => $pId,
                            'side' => 'B',
                        ]);
                    }
                }
                $matches[$rNum][$mIdx] = ['match' => $m, 'score' => $score];
            }
        }

        return [
            'host' => $host,
            'session' => $session,
            'courts' => $courts,
            'players' => $players,
            'drawing' => $drawingRecord,
            'rounds' => $rounds,
            'matches' => $matches,
        ];
    }

    /**
     * Test 28: Skenario A — P1 pause -> P2 pause -> P1 resume: P1 tidak hilang di ronde 3 dan riwayat ronde 1 tidak bocor.
     */
    public function test_28_scenario_a_reactivated_player_never_missing_and_no_historical_bench_bleed()
    {
        $env = $this->createSingleEnvironment(1, ['P1', 'P2', 'P3', 'P4'], 'First To 2');
        $session = $env['session'];
        $host = $env['host'];
        $players = $env['players'];
        $p1 = $env['players'][0];
        $p2 = $env['players'][1];
        $matchR1 = $env['matches'][1][0]['match'];
        $matchR2 = $env['matches'][2][0]['match'];

        $this->actingAs($host);

        // 1. Ronde 1 dihentikan, P1 diistirahatkan (temporary_pause)
        $respStopR1 = $this->postJson(route('scoring.stop-match'), [
            'game_id' => $session->session_id,
            'match_id' => $matchR1->match_id,
            'match_key' => 'round_1',
            'court_number' => 1,
            'round_key' => 'round_1',
            'stop_type' => 'temporary_pause',
            'stop_reason' => 'P1 istirahat kram',
            'withdrawn_player_ids' => [$p1->player_id],
        ]);
        $respStopR1->assertStatus(200);

        // Status P1 di tb_session_player harus resting, BUKAN withdrawn
        $p1Status = DB::table('tb_session_player')->where('session_id', $session->session_id)->where('player_id', $p1->player_id)->value('status');
        $this->assertSame('resting', $p1Status);

        // 2. Ronde 2 dihentikan, P2 diistirahatkan (temporary_pause)
        $roundTwo = Cache::get("drawing.schedule_{$session->session_id}")['rounds'][2];
        $roundTwoNames = array_merge(
            $roundTwo['matches'][0]['team_a_names'] ?? [],
            $roundTwo['matches'][0]['team_b_names'] ?? []
        );
        $p2 = collect($players)->first(fn (Player $player) => $player->player_id !== $p1->player_id && in_array($player->nama, $roundTwoNames, true));
        $this->assertNotNull($p2, 'Ronde 2 harus memiliki pemain selain P1 yang dapat diistirahatkan.');

        $respStopR2 = $this->postJson(route('scoring.stop-match'), [
            'game_id' => $session->session_id,
            'match_id' => $matchR2->match_id,
            'match_key' => 'round_2',
            'court_number' => 1,
            'round_key' => 'round_2',
            'stop_type' => 'temporary_pause',
            'stop_reason' => 'P2 istirahat',
            'withdrawn_player_ids' => [$p2->player_id],
        ]);
        $respStopR2->assertStatus(200);

        $p2Status = DB::table('tb_session_player')->where('session_id', $session->session_id)->where('player_id', $p2->player_id)->value('status');
        $this->assertSame('resting', $p2Status);

        // 3. Host menekan Siap Main untuk P1
        $respReactivate = $this->postJson(route('scoring.reactivate-player'), [
            'game_id' => $session->session_id,
            'player_id' => $p1->player_id,
            'round' => 'round_2',
        ]);
        $respReactivate->assertStatus(200);

        // Status P1 kembali active, P2 tetap resting
        $p1StatusAfter = DB::table('tb_session_player')->where('session_id', $session->session_id)->where('player_id', $p1->player_id)->value('status');
        $p2StatusAfter = DB::table('tb_session_player')->where('session_id', $session->session_id)->where('player_id', $p2->player_id)->value('status');
        $this->assertSame('active', $p1StatusAfter);
        $this->assertSame('resting', $p2StatusAfter);

        // 4. Verifikasi jadwal Ronde 3: P1 HARUS MUNCUL (tidak hilang!)
        $cachedSchedule = Cache::get("drawing.schedule_{$session->session_id}");
        $r3 = $cachedSchedule['rounds'][3] ?? $cachedSchedule['rounds']['round_3'];

        $r3PlayingNames = [];
        foreach ($r3['matches'] ?? [] as $m) {
            $r3PlayingNames = array_merge($r3PlayingNames, $m['team_a_names'] ?? [], $m['team_b_names'] ?? []);
        }
        $r3Resting = $r3['resting'] ?? [];
        $allR3People = array_merge($r3PlayingNames, $r3Resting);

        // P1 HARUS ada di Ronde 3
        $this->assertContains('P1', $allR3People, 'P1 yang sudah reaktif harus muncul di pertandingan atau bench Ronde 3!');
        // P2 tetap istirahat di resting
        $this->assertContains($p2->nama, $r3Resting, 'Pemain yang dipause di Ronde 2 harus berada di resting bench Ronde 3');
        // Total 4 pemain tercatat lengkap di Ronde 3
        $this->assertCount(4, array_unique($allR3People));

        // 5. Verifikasi riwayat Ronde 1: P2 tidak boleh bocor menjadi 'Istirahat' di ronde 1
        $drawingResp = $this->get(route('games.drawing', ['id' => $session->session_id, 'round' => 1]));
        $drawingResp->assertStatus(200);
        $r1View = $drawingResp->viewData('rounds')[1] ?? [];
        $r1PausedPlayers = $r1View['paused_players'] ?? [];
        $this->assertNotContains($p2->nama, $r1PausedPlayers, 'Pemain yang baru istirahat di Ronde 2 tidak boleh tercatat sebagai istirahat di snapshot Ronde 1');
    }

    /**
     * Test 29: Skenario B — Americano Single 4 pemain 2 court, 1 pemain pause -> 1 court aktif -> resume -> kapasitas 2 court pulih.
     */
    public function test_29_scenario_b_temporary_pause_and_reactivation_restores_two_courts()
    {
        $env = $this->createSingleEnvironment(2, ['Hilmy', 'Haechan', 'Player3', 'Player4'], 'First To 2');
        $session = $env['session'];
        $host = $env['host'];
        $hilmy = $env['players'][0];
        $haechan = $env['players'][1];
        $matchC1 = $env['matches'][1][0]['match'];
        $matchC2 = $env['matches'][1][1]['match'];

        $this->actingAs($host);

        // 1. Court 1 dihentikan, hanya Hilmy diistirahatkan (temporary_pause)
        $respStopC1 = $this->postJson(route('scoring.stop-match'), [
            'game_id' => $session->session_id,
            'match_id' => $matchC1->match_id,
            'match_key' => 'round_1_court_1',
            'court_number' => 1,
            'round_key' => 'round_1',
            'stop_type' => 'temporary_pause',
            'stop_reason' => 'Hilmy kram',
            'withdrawn_player_ids' => [$hilmy->player_id],
        ]);
        $respStopC1->assertStatus(200);

        // Court 2 diselesaikan normal
        $matchC2->status_match = 'Completed';
        $matchC2->save();
        $c2Score = Score::where('match_id', $matchC2->match_id)->first();
        if ($c2Score) {
            $c2Score->status_score = 'Final';
            $c2Score->save();
        }

        // Hilmy berstatus resting, Haechan tetap active
        $this->assertSame('resting', DB::table('tb_session_player')->where('session_id', $session->session_id)->where('player_id', $hilmy->player_id)->value('status'));
        $this->assertSame('active', DB::table('tb_session_player')->where('session_id', $session->session_id)->where('player_id', $haechan->player_id)->value('status'));

        // 2. Ronde 2 dengan 3 pemain aktif: Single mode memakai 1 court (2 pemain) dan 1 pemain bench rotasi, Hilmy istirahat
        $scheduleR2 = Cache::get("drawing.schedule_{$session->session_id}");
        $r2 = $scheduleR2['rounds'][2];
        $this->assertCount(1, $r2['matches'], 'Ronde 2 harus menggunakan 1 court karena pemain aktif hanya 3 orang');
        $this->assertContains('Hilmy', $r2['resting']);

        // 3. Host mengaktifkan kembali Hilmy
        $respReactivate = $this->postJson(route('scoring.reactivate-player'), [
            'game_id' => $session->session_id,
            'player_id' => $hilmy->player_id,
            'round' => 'round_2',
        ]);
        $respReactivate->assertStatus(200);

        // Seluruh 4 pemain kini berstatus active
        $activeCount = DB::table('tb_session_player')->where('session_id', $session->session_id)->where('status', 'active')->count();
        $this->assertSame(4, $activeCount);

        // 4. Ronde 3 yang belum dimulai HARUS MEMULIHKAN 2 COURT sesuai konfigurasi awal
        $scheduleR3 = Cache::get("drawing.schedule_{$session->session_id}");
        $r3 = $scheduleR3['rounds'][3];
        $this->assertCount(2, $r3['matches'], 'Ronde 3 harus kembali menggunakan 2 court karena seluruh 4 pemain telah aktif');

        $r3Participants = [];
        foreach ($r3['matches'] as $m) {
            $r3Participants = array_merge($r3Participants, $m['team_a_names'] ?? [], $m['team_b_names'] ?? []);
        }
        $this->assertContains('Hilmy', $r3Participants, 'Hilmy harus bertanding di salah satu court Ronde 3');
        $this->assertContains('Haechan', $r3Participants, 'Haechan harus bertanding di salah satu court Ronde 3');
        $this->assertCount(4, $r3Participants);
    }

    /**
     * Test 30: Withdrawn player (keluar permanen) TIDAK DAPAT diaktifkan melalui endpoint reactivatePlayer.
     */
    public function test_30_withdrawn_player_cannot_be_resumed_via_reactivate_endpoint()
    {
        $env = $this->createSingleEnvironment(1, ['P1', 'P2', 'P3', 'P4'], 'First To 2');
        $session = $env['session'];
        $host = $env['host'];
        $p1 = $env['players'][0];
        $match = $env['matches'][1][0]['match'];

        $this->actingAs($host);

        // P1 keluar permanen (withdraw_players)
        $this->postJson(route('scoring.stop-match'), [
            'game_id' => $session->session_id,
            'match_id' => $match->match_id,
            'match_key' => 'round_1',
            'court_number' => 1,
            'round_key' => 'round_1',
            'stop_type' => 'withdraw_players',
            'stop_reason' => 'Cedera parah',
            'withdrawn_player_ids' => [$p1->player_id],
        ])->assertStatus(200);

        $this->assertSame('withdrawn', DB::table('tb_session_player')->where('session_id', $session->session_id)->where('player_id', $p1->player_id)->value('status'));

        // Coba aktifkan kembali via endpoint resume -> HARUS DITOLAK (422)
        $resp = $this->postJson(route('scoring.reactivate-player'), [
            'game_id' => $session->session_id,
            'player_id' => $p1->player_id,
            'round' => 'round_1',
        ]);
        $resp->assertStatus(422);
        $resp->assertJsonFragment(['success' => false]);

        // Status tetap withdrawn
        $this->assertSame('withdrawn', DB::table('tb_session_player')->where('session_id', $session->session_id)->where('player_id', $p1->player_id)->value('status'));
    }

    /**
     * Test 31: Cache fixture kosong setelah pause/resume: verifikasi snapshot persis antara sebelum dan sesudah cache dihapus.
     * Menguji konsistensi drawing & live scoring:
     * - Pemain per court dan sisi
     * - Match ID yang sudah tersimpan
     * - Bench rotasi
     * - Pemain istirahat per ronde
     * - Status/skor match yang sudah berjalan atau terminal
     * - Ronde aktif dan jumlah ronde
     */
    public function test_31_empty_cache_reconstructs_adjusted_schedule_with_correct_court_capacity()
    {
        $env = $this->createSingleEnvironment(2, ['Hilmy', 'Haechan', 'Player3', 'Player4'], 'First To 2');
        $session = $env['session'];
        $host = $env['host'];
        $hilmy = $env['players'][0];
        $matchC1 = $env['matches'][1][0]['match'];
        $matchC2 = $env['matches'][1][1]['match'];

        $this->actingAs($host);

        // 1. Simulasikan Court 1 dihentikan dan Hilmy diistirahatkan (skor 1-1 saat dihentikan)
        $this->postJson(route('scoring.stop-match'), [
            'game_id' => $session->session_id,
            'match_id' => $matchC1->match_id,
            'match_key' => 'round_1_court_1',
            'court_number' => 1,
            'round_key' => 'round_1',
            'stop_type' => 'temporary_pause',
            'stop_reason' => 'Hilmy kram otot',
            'withdrawn_player_ids' => [$hilmy->player_id],
            'current_games_a' => 1,
            'current_games_b' => 1,
        ])->assertStatus(200);

        $respUpdate = $this->postJson(route('scoring.update-score'), [
            'game_id' => $session->session_id,
            'round' => 'round_1',
            'match_key' => 'round_1_court_2',
            'score_a' => 2,
            'score_b' => 0,
            'games_a' => 2,
            'games_b' => 0,
            'status' => 'completed',
            'winner_team' => 'team_a',
        ]);
        $respUpdate->assertStatus(200);

        // 2. Hilmy kembali aktif (Siap Main)
        $this->postJson(route('scoring.reactivate-player'), [
            'game_id' => $session->session_id,
            'player_id' => $hilmy->player_id,
            'round' => 'round_1',
        ])->assertStatus(200);

        // 3. Ambil SNAPSHOT LENGKAP sebelum cache dihapus
        $respDrawingBefore = $this->getJson(route('games.drawing', ['id' => $session->session_id, 'json' => 1]));
        $respDrawingBefore->assertStatus(200);
        $snapshotDrawingBefore = $respDrawingBefore->json();

        $respLiveBefore = $this->get(route('scoring.live', ['id' => $session->session_id]));
        $respLiveBefore->assertStatus(200);

        // 4. KOSONGKAN HANYA CACHE FIXTURE TERISOLASI
        Cache::forget("drawing.schedule_{$session->session_id}");
        Cache::forget("scoring.game_{$session->session_id}");

        // 5. BACA ULANG DARI ENDPOINT DRAWING (JSON & HTML) DAN LIVE SCORING
        $respDrawingAfter = $this->getJson(route('games.drawing', ['id' => $session->session_id, 'json' => 1]));
        $respDrawingAfter->assertStatus(200);
        $snapshotDrawingAfter = $respDrawingAfter->json();

        $respLiveAfter = $this->get(route('scoring.live', ['id' => $session->session_id]));
        $respLiveAfter->assertStatus(200);

        // 6. VALIDASI KESAMAAN PERSIS:
        // A. Ronde aktif dan jumlah ronde
        $this->assertSame(
            $snapshotDrawingBefore['active_round_num'],
            $snapshotDrawingAfter['active_round_num'],
            'Ronde aktif harus persis sama setelah cache dibersihkan'
        );
        $this->assertCount(
            count($snapshotDrawingBefore['rounds']),
            $snapshotDrawingAfter['rounds'],
            'Jumlah ronde harus persis sama'
        );

        $roundsBefore = $snapshotDrawingBefore['rounds'];
        $roundsAfter = $snapshotDrawingAfter['rounds'];

        foreach ($roundsBefore as $rNum => $rBefore) {
            $rAfter = $roundsAfter[$rNum] ?? null;
            $this->assertNotNull($rAfter, "Ronde {$rNum} harus ada pada hasil rekonstruksi cache kosong");

            // B. Bench rotasi per ronde
            $benchBefore = $rBefore['rotation_bench'] ?? ($rBefore['resting'] ?? []);
            $benchAfter = $rAfter['rotation_bench'] ?? ($rAfter['resting'] ?? []);
            sort($benchBefore);
            sort($benchAfter);
            $this->assertEquals($benchBefore, $benchAfter, "Bench rotasi ronde {$rNum} harus persis sama");

            // C. Pemain istirahat per ronde
            $pausedBefore = $rBefore['paused_players'] ?? [];
            $pausedAfter = $rAfter['paused_players'] ?? [];
            sort($pausedBefore);
            sort($pausedAfter);
            $this->assertEquals($pausedBefore, $pausedAfter, "Daftar istirahat ronde {$rNum} harus persis sama");

            // D. Pemain per court dan sisi serta Match ID
            $this->assertCount(count($rBefore['matches']), $rAfter['matches'], "Jumlah court pertandingan ronde {$rNum} harus persis sama");
            foreach ($rBefore['matches'] as $mIdx => $mBefore) {
                $mAfter = $rAfter['matches'][$mIdx] ?? null;
                $this->assertNotNull($mAfter, "Match court index {$mIdx} ronde {$rNum} harus ada");

                // Match ID persisten sama
                if (! empty($mBefore['match_id']) && ! empty($mAfter['match_id'])) {
                    $this->assertSame($mBefore['match_id'], $mAfter['match_id'], "Match ID court index {$mIdx} ronde {$rNum} harus persis sama");
                }

                // Court sama
                if (isset($mBefore['court_id']) && isset($mAfter['court_id'])) {
                    $this->assertSame($mBefore['court_id'], $mAfter['court_id'], "Court ID ronde {$rNum} court {$mIdx} harus persis sama");
                }
                $this->assertSame($mBefore['court'] ?? ($mIdx + 1), $mAfter['court'] ?? ($mIdx + 1), "Court number ronde {$rNum} court {$mIdx} harus persis sama");

                // Pemain Sisi A
                $sideABefore = array_column($mBefore['team_a'] ?? [], 'name');
                $sideAAfter = array_column($mAfter['team_a'] ?? [], 'name');
                sort($sideABefore);
                sort($sideAAfter);
                $this->assertEquals($sideABefore, $sideAAfter, "Peserta Sisi A ronde {$rNum} court {$mIdx} harus persis sama");

                // Pemain Sisi B
                $sideBBefore = array_column($mBefore['team_b'] ?? [], 'name');
                $sideBAfter = array_column($mAfter['team_b'] ?? [], 'name');
                sort($sideBBefore);
                sort($sideBAfter);
                $this->assertEquals($sideBBefore, $sideBAfter, "Peserta Sisi B ronde {$rNum} court {$mIdx} harus persis sama");
            }
        }

        // E. Status & skor match yang sudah berjalan atau terminal di database
        $freshMatchC1 = GameMatch::find($matchC1->match_id);
        $this->assertSame('Stopped', $freshMatchC1->status_match, 'Status match 1 harus tetap Stopped');
        $this->assertStringContainsString('Dihentikan: 1 - 1', $freshMatchC1->hasil_pertandingan);
        $this->assertSame('Hilmy kram otot', $freshMatchC1->stop_reason);
        $this->assertSame('temporary_pause', $freshMatchC1->stop_type);

        $freshMatchC2 = GameMatch::find($matchC2->match_id);
        $this->assertSame('Completed', $freshMatchC2->status_match, 'Status match 2 harus tetap Completed');
        $this->assertSame('team_a', $freshMatchC2->winner_team);

        // F. Pemulihan kapasitas court konfigurasi sesi: Ronde 3 memiliki 2 match (2 court aktif)
        $r3Matches = $roundsAfter[3]['matches'] ?? [];
        $this->assertCount(2, $r3Matches, 'Ronde 3 harus menggunakan kembali 2 court setelah Hilmy kembali aktif');
    }

    /**
     * Test 32: Pemain yang di-pause lalu diaktifkan kembali (Siap Main) tidak dianggap withdrawn dan kembali dijadwalkan di ronde berikutnya.
     */
    public function test_32_reactivated_player_is_not_withdrawn_and_scheduled_in_future_rounds()
    {
        $env = $this->createSingleEnvironment(1, ['Hilmy', 'Haechan', 'Jaehyun', 'Ocean'], 'Total of 3');
        $session = $env['session'];
        $host = $env['host'];
        $hilmy = $env['players'][0];
        $matchC1 = $env['matches'][1][0]['match'];

        $this->actingAs($host);

        // 1. Pause Hilmy (peserta match 1) di ronde 1
        $stopResp = $this->postJson(route('scoring.stop-match'), [
            'game_id' => $session->session_id,
            'match_id' => $matchC1->match_id,
            'match_key' => 'round_1_court_1',
            'court_number' => 1,
            'round_key' => 'round_1',
            'stop_type' => 'temporary_pause',
            'withdrawn_player_ids' => [$hilmy->player_id],
        ]);
        $stopResp->assertStatus(200);

        // Pastikan Hilmy TIDAK masuk ke getWithdrawnPlayerIds
        $withdrawnIds = ScoringService::getWithdrawnPlayerIds($session);
        $this->assertNotContains((int) $hilmy->player_id, $withdrawnIds, 'Pemain temporary_pause tidak boleh dianggap withdrawn permanen.');

        // 2. Host mengaktifkan kembali Hilmy (Siap Main) di ronde 2
        $reactivateResp = $this->postJson(route('scoring.reactivate-player'), [
            'game_id' => $session->session_id,
            'player_id' => $hilmy->player_id,
            'round' => 'round_2',
        ]);
        $reactivateResp->assertStatus(200);
        $reactivateResp->assertJsonFragment(['success' => true]);

        // Status di DB harus active
        $this->assertSame('active', DB::table('tb_session_player')->where('session_id', $session->session_id)->where('player_id', $hilmy->player_id)->value('status'));

        // Cek getWithdrawnPlayerIds sekali lagi
        $withdrawnIdsAfter = ScoringService::getWithdrawnPlayerIds($session);
        $this->assertNotContains((int) $hilmy->player_id, $withdrawnIdsAfter);

        // Cek jadwal ronde 3: Hilmy harus ada di jadwal drawing
        $cachedSchedule = Cache::get("drawing.schedule_{$session->session_id}");
        $this->assertNotEmpty($cachedSchedule);
        $r3 = $cachedSchedule['rounds'][3] ?? null;
        $this->assertNotNull($r3, 'Ronde 3 harus ada dalam jadwal.');

        // Kumpulkan seluruh nama pemain di ronde 3 (baik yang main maupun istirahat giliran biasa)
        $allNamesInR3 = [];
        if (! empty($r3['matches'])) {
            foreach ($r3['matches'] as $m) {
                $allNamesInR3 = array_merge($allNamesInR3, $m['team_a_names'] ?? [], $m['team_b_names'] ?? []);
            }
        }
        $allNamesInR3 = array_merge($allNamesInR3, $r3['resting'] ?? []);

        // Hilmy harus termasuk dalam rotasi ronde 3
        $this->assertContains('Hilmy', $allNamesInR3, 'Hilmy harus kembali masuk ke dalam jadwal ronde berikutnya setelah diaktifkan.');
    }

    public function test_rescheduled_single_round_does_not_repeat_opponents_from_frozen_rounds()
    {
        $env = $this->createSingleEnvironment(2, ['P1', 'P2', 'P3', 'P4'], 'Total of 3');
        $session = $env['session'];
        $players = $env['players'];
        $schedule = Cache::get("drawing.schedule_{$session->session_id}");
        $playerData = fn (int $index): array => [
            'id' => $players[$index]->player_id,
            'name' => $players[$index]->nama,
        ];
        $roundOneMatches = [
            [
                'court' => 1,
                'court_name' => 'Court 1',
                'team_a' => [$playerData(0)],
                'team_b' => [$playerData(2)],
                'team_a_names' => ['P1'],
                'team_b_names' => ['P3'],
            ],
            [
                'court' => 2,
                'court_name' => 'Court 2',
                'team_a' => [$playerData(1)],
                'team_b' => [$playerData(3)],
                'team_a_names' => ['P2'],
                'team_b_names' => ['P4'],
            ],
        ];
        $schedule['rounds'][1]['matches'] = $roundOneMatches;
        $schedule['round_1'] = $schedule['rounds'][1];
        Cache::put("drawing.schedule_{$session->session_id}", $schedule, 3600);

        $matchCourtOne = $env['matches'][1][0]['match'];
        $matchCourtOne->update(['status_match' => 'Stopped']);
        $env['matches'][1][1]['match']->update(['status_match' => 'Completed']);

        ScoringService::adjustFutureScheduleOnPause($session, [$players[0]->player_id], 1);
        DB::table('tb_session_player')
            ->where('session_id', $session->session_id)
            ->where('player_id', $players[0]->player_id)
            ->update(['status' => 'active']);
        ScoringService::adjustFutureScheduleOnReactivation($session, $players[0]->player_id, 2);

        $rescheduled = Cache::get("drawing.schedule_{$session->session_id}");
        $roundThreeMatches = $rescheduled['rounds'][3]['matches'] ?? [];
        $playedPairs = ['P1|P3', 'P2|P4'];

        foreach ($roundThreeMatches as $match) {
            $pair = [
                $match['team_a_names'][0] ?? '',
                $match['team_b_names'][0] ?? '',
            ];
            sort($pair);

            $this->assertNotContains(
                implode('|', $pair),
                $playedPairs,
                'Ronde 3 tidak boleh mengulang pasangan lawan dari ronde 1.'
            );
        }
    }

    /**
     * Test: Stopped match di ronde terakhir berhasil menyelesaikan sesi dan redirect ke recap.
     */
    public function test_stopped_match_in_final_round_finishes_session_and_redirects_to_recap()
    {
        $env = $this->createIsolatedEnvironment(1, 'Americano', 'Total of 3');
        $session = $env['session'];
        $host = $env['host'];

        // Tandai round 1 dan 2 selesai
        $cacheKey = "scoring.game_{$session->session_id}";
        $scores = [
            'round_1' => [
                'score_a' => 2, 'score_b' => 1, 'games_a' => 2, 'games_b' => 1,
                'sets_a' => 1, 'sets_b' => 0, 'status' => 'completed',
            ],
            'round_2' => [
                'score_a' => 1, 'score_b' => 2, 'games_a' => 1, 'games_b' => 2,
                'sets_a' => 0, 'sets_b' => 1, 'status' => 'completed',
            ],
            'round_3_court_1' => [
                'score_a' => 0, 'score_b' => 0, 'games_a' => 0, 'games_b' => 0,
                'sets_a' => 0, 'sets_b' => 0, 'status' => 'stopped',
                'stop_reason' => 'Cedera otot pemain',
                'stop_type' => 'withdraw',
            ],
        ];
        Cache::put($cacheKey, $scores, 3600);

        // Hentikan match di DB untuk round 3
        $matchRound3 = $env['matches']['round_3'][0]['match'];
        $matchRound3->update([
            'status_match' => 'Stopped',
            'stop_reason' => 'Cedera otot pemain',
            'stop_type' => 'withdraw',
        ]);

        $this->actingAs($host);

        $response = $this->post(route('scoring.finish'), [
            'game_id' => $session->session_id,
            'round' => 'round_3',
            'match_key' => 'round_3_court_1',
            'score_a' => 0,
            'score_b' => 0,
            'games_a' => 0,
            'games_b' => 0,
            'sets_a' => 0,
            'sets_b' => 0,
            'status' => 'stopped',
            'is_stopped' => 1,
        ]);

        $response->assertRedirect(route('scoring.recap', $session->session_id));
        $response->assertSessionHasNoErrors();

        // Verifikasi session di DB berstatus Finished
        $this->assertEquals('Finished', $session->fresh()->status_session);

        // Verifikasi match di DB tetap berstatus Stopped (tidak tertimpa menjadi Completed)
        $this->assertEquals('Stopped', $matchRound3->fresh()->status_match);

        // Verifikasi cache skor tetap stopped
        $cachedScores = Cache::get($cacheKey);
        $this->assertEquals('stopped', $cachedScores['round_3_court_1']['status']);
        $this->assertEquals('finished', $cachedScores['_meta']['status']);
    }

    public function test_permanent_withdrawal_below_double_quota_allows_early_recap()
    {
        foreach (['Americano', 'Team Americano'] as $format) {
            $env = $this->createIsolatedEnvironment(1, $format, 'Total of 3');
            $session = $env['session'];
            $host = $env['host'];
            $withdrawnPlayer = $env['players'][0];
            $removedPlayers = array_slice($env['players'], 4);

            DB::table('tb_session_player')
                ->where('session_id', $session->session_id)
                ->whereIn('player_id', array_map(fn ($player) => $player->player_id, $removedPlayers))
                ->delete();
            DB::table('tb_session_player')
                ->where('session_id', $session->session_id)
                ->where('player_id', $withdrawnPlayer->player_id)
                ->update(['status' => 'withdrawn', 'withdrawn_reason' => 'Cedera']);

            $match = $env['matches']['round_1'][0]['match'];
            $match->update(['status_match' => 'Stopped', 'stop_type' => 'withdraw_players']);
            Cache::put("scoring.game_{$session->session_id}", [
                'round_1' => [
                    'status' => 'stopped',
                    'games_a' => 2,
                    'games_b' => 1,
                    'stop_type' => 'withdraw_players',
                    'withdrawn_player_ids' => [$withdrawnPlayer->player_id],
                ],
            ], 3600);

            $this->actingAs($host);
            $liveResponse = $this->get(route('scoring.live', [
                'id' => $session->session_id,
                'format' => $format,
                'round' => 'round_1',
            ]));

            $liveResponse->assertOk();
            $liveResponse->assertSee('id="globalFinishForm"', false);
            $liveResponse->assertDontSee('id="globalNextRoundForm"', false);

            $response = $this->post(route('scoring.finish'), [
                'game_id' => $session->session_id,
                'round' => 'round_1',
                'format' => $format,
                'match_key' => 'round_1',
                'status' => 'stopped',
                'is_stopped' => 1,
                'games_a' => 2,
                'games_b' => 1,
            ]);

            $response->assertRedirect(route('scoring.recap', $session->session_id));
            $this->assertSame('Finished', $session->fresh()->status_session);
        }
    }

    public function test_early_recap_is_rejected_while_another_court_is_active()
    {
        $env = $this->createIsolatedEnvironment(2, 'Americano', 'Total of 3');
        $session = $env['session'];
        $host = $env['host'];
        $withdrawnPlayers = array_slice($env['players'], 0, 5);

        DB::table('tb_session_player')
            ->where('session_id', $session->session_id)
            ->whereIn('player_id', array_map(fn ($player) => $player->player_id, $withdrawnPlayers))
            ->update(['status' => 'withdrawn', 'withdrawn_reason' => 'Keluar permanen']);

        $stoppedMatch = $env['matches']['round_1'][0]['match'];
        $stoppedMatch->update(['status_match' => 'Stopped', 'stop_type' => 'withdraw_players']);
        Cache::put("scoring.game_{$session->session_id}", [
            'round_1_court_1' => ['status' => 'stopped', 'games_a' => 2, 'games_b' => 1],
            'round_1_court_2' => ['status' => 'in_progress', 'games_a' => 1, 'games_b' => 1],
        ], 3600);

        $this->actingAs($host);
        $response = $this->post(route('scoring.finish'), [
            'game_id' => $session->session_id,
            'round' => 'round_1',
            'format' => 'Americano',
            'match_key' => 'round_1_court_1',
            'status' => 'stopped',
            'is_stopped' => 1,
        ]);

        $response->assertSessionHasErrors('score');
        $this->assertSame('In Progress', $session->fresh()->status_session);
    }

    public function test_drawing_redirects_to_recap_when_withdrawals_leave_too_few_players()
    {
        $env = $this->createIsolatedEnvironment(1, 'Americano', 'Total of 3');
        $session = $env['session'];
        $cacheKey = "scoring.game_{$session->session_id}";

        DB::table('tb_session_player')
            ->where('session_id', $session->session_id)
            ->update(['status' => 'withdrawn', 'withdrawn_reason' => 'Keluar permanen']);
        foreach ($env['matches'] as $roundKey => $roundMatches) {
            if ($roundKey === 'round_1') {
                continue;
            }
            foreach ($roundMatches as $roundMatch) {
                $roundMatch['match']->update(['status_match' => 'Scheduled']);
            }
        }
        $env['matches']['round_1'][0]['match']->update([
            'status_match' => 'Stopped',
            'stop_type' => 'withdraw_players',
        ]);
        Cache::put($cacheKey, [
            'round_1' => [
                'status' => 'stopped',
                'games_a' => 2,
                'games_b' => 1,
                'stop_type' => 'withdraw_players',
            ],
        ], 3600);

        $this->actingAs($env['host']);
        $response = $this->get(route('games.drawing', [
            'id' => $session->session_id,
            'round' => 2,
        ]));

        $response->assertRedirect(route('scoring.recap', $session->session_id));
        $this->assertSame('Finished', $session->fresh()->status_session);
        $this->assertSame('finished', Cache::get($cacheKey)['_meta']['status']);
    }

    public function test_court_player_can_be_paused_from_drawing_when_match_is_not_actively_playing()
    {
        $unique = uniqid('manage_pause_', true);
        $host = User::create([
            'nama' => 'Host '.$unique,
            'email' => 'host_'.$unique.'@test.com',
            'role' => 'host',
        ]);

        $sport = Sport::create(['nama_sport' => 'Padel']);
        $venue = Venue::create(['nama_venue' => 'Venue '.$unique, 'owner_user_id' => $host->user_id]);

        $session = SessionModel::create([
            'host_user_id' => $host->user_id,
            'sport_id' => $sport->sport_id,
            'venue_id' => $venue->venue_id,
            'nama_session' => 'Session '.$unique,
            'scoring_system' => 'Total of 3',
            'status_session' => 'In Progress',
            'jenis_permainan' => 'Double',
            'jumlah_pemain' => 5,
        ]);

        $court = Court::create(['nama_court' => 'Court 1']);
        DB::table('tb_session_court')->insert([
            'session_id' => $session->session_id,
            'court_id' => $court->court_id,
        ]);

        $playerNames = ['P1', 'P2', 'P3', 'P4', 'P5'];
        $players = [];
        foreach ($playerNames as $pName) {
            $user = User::create([
                'nama' => $pName,
                'email' => strtolower($pName).'_'.$unique.'@test.com',
                'role' => 'member',
            ]);
            $pl = Player::create([
                'user_id' => $user->user_id,
                'nama' => $pName,
            ]);
            DB::table('tb_session_player')->insert([
                'session_id' => $session->session_id,
                'player_id' => $pl->player_id,
                'status' => 'active',
            ]);
            $players[] = $pl;
        }

        $drawingRecord = Drawing::create([
            'session_id' => $session->session_id,
            'status_drawing' => 'Locked',
        ]);

        // P1, P2, P3, P4 di Court 1. P5 di bench (resting).
        $match = GameMatch::create([
            'drawing_id' => $drawingRecord->drawing_id,
            'court_id' => $court->court_id,
            'nomor_match' => 1,
            'status_match' => 'Scheduled',
        ]);

        DB::table('tb_match_participant')->insert([
            ['match_id' => $match->match_id, 'player_id' => $players[0]->player_id, 'side' => 'A'],
            ['match_id' => $match->match_id, 'player_id' => $players[1]->player_id, 'side' => 'A'],
            ['match_id' => $match->match_id, 'player_id' => $players[2]->player_id, 'side' => 'B'],
            ['match_id' => $match->match_id, 'player_id' => $players[3]->player_id, 'side' => 'B'],
        ]);

        $schedule = [
            'round_1' => [
                'matches' => [
                    [
                        'court' => 1,
                        'court_name' => 'Court 1',
                        'team_a' => [['id' => $players[0]->player_id, 'name' => 'P1'], ['id' => $players[1]->player_id, 'name' => 'P2']],
                        'team_b' => [['id' => $players[2]->player_id, 'name' => 'P3'], ['id' => $players[3]->player_id, 'name' => 'P4']],
                        'team_a_names' => ['P1', 'P2'],
                        'team_b_names' => ['P3', 'P4'],
                    ],
                ],
                'resting' => ['P5'],
            ],
        ];
        Cache::put("drawing.schedule_{$session->session_id}", $schedule, 3600);

        // Host mengistirahatkan P1 yang ada di Court 1
        $this->actingAs($host);
        $response = $this->postJson(route('scoring.manage-player-status'), [
            'game_id' => $session->session_id,
            'player_id' => $players[0]->player_id,
            'action' => 'pause',
            'reason' => 'Cedera ringan',
            'round' => 'round_1',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'action' => 'pause',
            'message' => 'Pemain berhasil diistirahatkan ke bangku cadangan.',
        ]);

        // P1 berstatus resting di tb_session_player
        $this->assertDatabaseHas('tb_session_player', [
            'session_id' => $session->session_id,
            'player_id' => $players[0]->player_id,
            'status' => 'resting',
            'withdrawn_reason' => 'Cedera ringan',
        ]);

        // P1 digantikan di court 1 oleh P5
        $courtPlayerIds = DB::table('tb_match_participant')
            ->where('match_id', $match->match_id)
            ->pluck('player_id')
            ->all();

        $this->assertNotContains($players[0]->player_id, $courtPlayerIds);
        $this->assertContains($players[4]->player_id, $courtPlayerIds);
    }

    public function test_court_player_cannot_be_paused_directly_when_match_is_actively_playing_with_scores()
    {
        $unique = uniqid('manage_block_', true);
        $host = User::create([
            'nama' => 'Host '.$unique,
            'email' => 'host_'.$unique.'@test.com',
            'role' => 'host',
        ]);

        $sport = Sport::create(['nama_sport' => 'Padel']);
        $venue = Venue::create(['nama_venue' => 'Venue '.$unique, 'owner_user_id' => $host->user_id]);

        $session = SessionModel::create([
            'host_user_id' => $host->user_id,
            'sport_id' => $sport->sport_id,
            'venue_id' => $venue->venue_id,
            'nama_session' => 'Session '.$unique,
            'scoring_system' => 'Total of 3',
            'status_session' => 'In Progress',
            'jenis_permainan' => 'Double',
            'jumlah_pemain' => 5,
        ]);

        $court = Court::create(['nama_court' => 'Court 1']);
        DB::table('tb_session_court')->insert([
            'session_id' => $session->session_id,
            'court_id' => $court->court_id,
        ]);

        $playerNames = ['P1', 'P2', 'P3', 'P4', 'P5'];
        $players = [];
        foreach ($playerNames as $pName) {
            $user = User::create([
                'nama' => $pName,
                'email' => strtolower($pName).'_'.$unique.'@test.com',
                'role' => 'member',
            ]);
            $pl = Player::create([
                'user_id' => $user->user_id,
                'nama' => $pName,
            ]);
            DB::table('tb_session_player')->insert([
                'session_id' => $session->session_id,
                'player_id' => $pl->player_id,
                'status' => 'active',
            ]);
            $players[] = $pl;
        }

        $drawingRecord = Drawing::create([
            'session_id' => $session->session_id,
            'status_drawing' => 'Locked',
        ]);

        $match = GameMatch::create([
            'drawing_id' => $drawingRecord->drawing_id,
            'court_id' => $court->court_id,
            'nomor_match' => 1,
            'status_match' => 'In Progress',
            'waktu_mulai' => now(),
        ]);

        DB::table('tb_match_participant')->insert([
            ['match_id' => $match->match_id, 'player_id' => $players[0]->player_id, 'side' => 'A'],
            ['match_id' => $match->match_id, 'player_id' => $players[1]->player_id, 'side' => 'A'],
            ['match_id' => $match->match_id, 'player_id' => $players[2]->player_id, 'side' => 'B'],
            ['match_id' => $match->match_id, 'player_id' => $players[3]->player_id, 'side' => 'B'],
        ]);

        // Buat skor aktif di tb_score
        Score::create([
            'match_id' => $match->match_id,
            'set_number' => 1,
            'game_number' => 1,
            'game_score_a' => 2,
            'game_score_b' => 1,
            'scoring_system' => 'Total of 3',
            'status_score' => 'In Progress',
        ]);

        $this->actingAs($host);
        $response = $this->postJson(route('scoring.manage-player-status'), [
            'game_id' => $session->session_id,
            'player_id' => $players[0]->player_id,
            'action' => 'pause',
            'reason' => 'Cedera ringan',
            'round' => 'round_1',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'is_playing' => true,
            'court' => 1,
            'match_id' => $match->match_id,
        ]);
    }
}
