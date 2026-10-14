<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\Drawing;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\Score;
use App\Models\SessionModel;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use App\Services\Scoring\ScoringService;
use Illuminate\Support\Facades\Auth;
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

        $this->postJson(route('scoring.stop-match'), [
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

        $this->postJson(route('scoring.stop-match'), [
            'game_id' => $session->session_id,
            'match_id' => $targetMatch->match_id,
            'match_key' => 'round_1_court_1',
            'court_number' => 1,
            'round_key' => 'round_1',
            'stop_type' => 'keep_in_session',
            'stop_reason' => 'Hujan lebat',
            'current_games_a' => 5,
            'current_games_b' => 2,
        ])->assertStatus(200);

        // Buka halaman recap
        $recapResp = $this->get(route('scoring.recap', $session->session_id));
        $recapResp->assertStatus(200);
        $recapResp->assertViewHas('hasStoppedMatches', true);

        // Verifikasi bahwa match dihentikan tidak dianggap menang sepihak untuk Alice/Bob
        $ranked = $recapResp->viewData('rankedPlayers');
        $aliceStat = collect($ranked)->firstWhere('name', 'Alice');
        // Match yang dihentikan dipisahkan dari agregasi kemenangan normal
        $this->assertSame(0, $aliceStat['wins'] ?? 0);
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
}

