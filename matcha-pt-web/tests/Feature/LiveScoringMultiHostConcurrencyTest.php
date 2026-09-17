<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\Player;
use App\Models\SessionModel;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LiveScoringMultiHostConcurrencyTest extends TestCase
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
                $table->unsignedBigInteger('match_format_id')->nullable();
                $table->date('tanggal_drawing')->nullable();
                $table->time('jam_drawing')->nullable();
                $table->string('status_drawing')->default('Locked');
                $table->timestamps();
            });
        } else {
            // Ensure updated columns exist (migration guard)
            if (! Schema::hasColumn('tb_drawing', 'match_format_id')) {
                Schema::table('tb_drawing', function ($table) {
                    $table->unsignedBigInteger('match_format_id')->nullable();
                });
            }
            if (! Schema::hasColumn('tb_drawing', 'tanggal_drawing')) {
                Schema::table('tb_drawing', function ($table) {
                    $table->date('tanggal_drawing')->nullable();
                });
            }
            if (! Schema::hasColumn('tb_drawing', 'jam_drawing')) {
                Schema::table('tb_drawing', function ($table) {
                    $table->time('jam_drawing')->nullable();
                });
            }
        }

        if (! Schema::hasTable('tb_match_participant')) {
            Schema::create('tb_match_participant', function ($table) {
                $table->unsignedBigInteger('match_id');
                $table->unsignedBigInteger('player_id');
                $table->string('side')->nullable();
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
                $table->integer('version')->default(0);
                $table->string('last_event_id', 128)->nullable();
                $table->timestamps();
            });
        } else {
            if (! Schema::hasColumn('tb_match', 'version')) {
                Schema::table('tb_match', function ($table) {
                    $table->integer('version')->default(0);
                });
            }
            if (! Schema::hasColumn('tb_match', 'last_event_id')) {
                Schema::table('tb_match', function ($table) {
                    $table->string('last_event_id', 128)->nullable();
                });
            }
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
                $table->integer('version')->default(0);
                $table->string('last_event_id', 128)->nullable();
                $table->timestamps();
            });
        } else {
            if (! Schema::hasColumn('tb_score', 'version')) {
                Schema::table('tb_score', function ($table) {
                    $table->integer('version')->default(0);
                });
            }
            if (! Schema::hasColumn('tb_score', 'last_event_id')) {
                Schema::table('tb_score', function ($table) {
                    $table->string('last_event_id', 128)->nullable();
                });
            }
        }
    }

    protected function createTestSession($courtCount = 2)
    {
        $hostUser = User::create([
            'nama' => 'Host User 1',
            'email' => 'host1_'.uniqid().'@matcha.com',
            'role' => 'host',
            'password' => bcrypt('secret'),
        ]);

        $sport = Sport::firstOrCreate(['nama_sport' => 'Padel'], ['status_sport' => 'active']);
        $venue = Venue::create(['nama_venue' => 'Arena Multi Host']);

        $session = SessionModel::create([
            'host_user_id' => $hostUser->user_id,
            'sport_id' => $sport->sport_id,
            'venue_id' => $venue->venue_id,
            'nama_session' => 'Mabar Multi Host',
            'scoring_system' => 'Total of 3',
            'status_session' => 'In Progress',
            'jenis_permainan' => 'Double',
            'jumlah_pemain' => 8,
        ]);

        for ($c = 1; $c <= $courtCount; $c++) {
            $court = Court::create(['nama_court' => "Court {$c}"]);
            DB::table('tb_session_court')->insert([
                'session_id' => $session->session_id,
                'court_id' => $court->court_id,
            ]);
        }

        $hostPlayer = Player::create([
            'user_id' => $hostUser->user_id,
            'nama' => 'Host Player 1',
            'gender' => 'Male',
            'level' => 'Intermediate',
        ]);
        DB::table('tb_session_player')->insert([
            'session_id' => $session->session_id,
            'player_id' => $hostPlayer->player_id,
        ]);

        // Second Host / Participant user
        $scorerUser = User::create([
            'nama' => 'Scorer User 2',
            'email' => 'scorer2_'.uniqid().'@matcha.com',
            'role' => 'member',
            'password' => bcrypt('secret'),
        ]);
        $scorerPlayer = Player::create([
            'user_id' => $scorerUser->user_id,
            'nama' => 'Scorer Player 2',
            'gender' => 'Male',
            'level' => 'Intermediate',
        ]);
        DB::table('tb_session_player')->insert([
            'session_id' => $session->session_id,
            'player_id' => $scorerPlayer->player_id,
        ]);

        for ($p = 3; $p <= 8; $p++) {
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
            DB::table('tb_session_player')->insert([
                'session_id' => $session->session_id,
                'player_id' => $player->player_id,
            ]);
        }

        return [$session, $hostUser, $scorerUser];
    }

    /**
     * Test A: Single Host - penambahan point bertahap menghasilkan score dan sequence yang benar
     */
    public function test_single_host_point_addition(): void
    {
        [$session, $hostUser] = $this->createTestSession(1);

        $res1 = $this->actingAs($hostUser)->postJson(route('scoring.update-score'), [
            'game_id' => $session->session_id,
            'round' => 'round_1',
            'match_key' => 'round_1_court_1',
            'court' => 1,
            'client_id' => 'client_host_1',
            'client_seq' => 1,
            'event_id' => 'evt_test_1',
            'action' => 'add_point',
            'point_won_by' => 'A',
            'base_version' => 0,
            'score_a' => 0,
            'score_b' => 0,
            'point_display_a' => '15',
            'point_display_b' => '0',
            'idx_a' => 1,
            'idx_b' => 0,
        ]);

        $res1->assertOk()
            ->assertJson([
                'success' => true,
                'version' => 1,
                'client_seq' => 1,
                'stale_ignored' => false,
            ]);

        $this->assertEquals('15', $res1->json('saved.point_display_a'));
        $this->assertEquals('0', $res1->json('saved.point_display_b'));

        // Point kedua
        $res2 = $this->actingAs($hostUser)->postJson(route('scoring.update-score'), [
            'game_id' => $session->session_id,
            'round' => 'round_1',
            'match_key' => 'round_1_court_1',
            'court' => 1,
            'client_id' => 'client_host_1',
            'client_seq' => 2,
            'event_id' => 'evt_test_2',
            'action' => 'add_point',
            'point_won_by' => 'A',
            'base_version' => 1,
            'score_a' => 0,
            'score_b' => 0,
            'point_display_a' => '30',
            'point_display_b' => '0',
            'idx_a' => 2,
            'idx_b' => 0,
        ]);

        $res2->assertOk()
            ->assertJson([
                'success' => true,
                'version' => 2,
                'client_seq' => 2,
                'stale_ignored' => false,
            ]);

        $this->assertEquals('30', $res2->json('saved.point_display_a'));
    }

    /**
     * Test B: Multi Host - Host A (+A) dan Host B (+B) mencatat poin secara konkuren sebelum polling
     * Server harus mengakumulasikan kedua poin (3-Way Delta Merge)
     */
    public function test_multi_host_concurrent_scoring_different_teams(): void
    {
        [$session, $hostUser, $scorerUser] = $this->createTestSession(1);

        // State awal di server: 0 - 0 (version 0)
        // Host A menambah poin A dengan base_version = 0
        $resA = $this->actingAs($hostUser)->postJson(route('scoring.update-score'), [
            'game_id' => $session->session_id,
            'round' => 'round_1',
            'match_key' => 'round_1_court_1',
            'court' => 1,
            'client_id' => 'client_host_A',
            'client_seq' => 1,
            'event_id' => 'evt_host_a_1',
            'action' => 'add_point',
            'point_won_by' => 'A',
            'base_version' => 0,
            'score_a' => 0,
            'score_b' => 0,
            'point_display_a' => '15',
            'point_display_b' => '0',
            'idx_a' => 1,
            'idx_b' => 0,
        ]);

        $resA->assertOk();
        $this->assertEquals(1, $resA->json('version'));
        $this->assertEquals('15', $resA->json('saved.point_display_a'));
        $this->assertEquals('0', $resA->json('saved.point_display_b'));

        // Host B menambah poin B SEBELUM polling (melihat base_version = 0, padahal server sudah version 1)
        $resB = $this->actingAs($scorerUser)->postJson(route('scoring.update-score'), [
            'game_id' => $session->session_id,
            'round' => 'round_1',
            'match_key' => 'round_1_court_1',
            'court' => 1,
            'client_id' => 'client_host_B',
            'client_seq' => 1,
            'event_id' => 'evt_host_b_1',
            'action' => 'add_point',
            'point_won_by' => 'B',
            'base_version' => 0, // Concurrent divergence!
            'score_a' => 0,
            'score_b' => 0,
            'point_display_a' => '0',
            'point_display_b' => '15',
            'idx_a' => 0,
            'idx_b' => 1,
        ]);

        $resB->assertOk()
            ->assertJson([
                'success' => true,
                'merged' => true, // Konfirmasi 3-Way Delta Merge
                'version' => 2,
                'stale_ignored' => false,
            ]);

        // Kedua poin HARUS ada di server state (15 - 15)
        $this->assertEquals('15', $resB->json('saved.point_display_a'));
        $this->assertEquals('15', $resB->json('saved.point_display_b'));
    }

    /**
     * Test C: Multi Host same team - Host A (+A) dan Host B (+A) dengan event_id berbeda
     * Kedua poin harus masuk dan terakumulasi
     */
    public function test_multi_host_same_team_concurrent_scoring(): void
    {
        [$session, $hostUser, $scorerUser] = $this->createTestSession(1);

        // Host A menambah poin A
        $resA = $this->actingAs($hostUser)->postJson(route('scoring.update-score'), [
            'game_id' => $session->session_id,
            'round' => 'round_1',
            'match_key' => 'round_1_court_1',
            'court' => 1,
            'client_id' => 'client_host_A',
            'client_seq' => 1,
            'event_id' => 'evt_a_unique_1',
            'action' => 'add_point',
            'point_won_by' => 'A',
            'base_version' => 0,
            'score_a' => 0,
            'score_b' => 0,
            'point_display_a' => '15',
            'point_display_b' => '0',
            'idx_a' => 1,
            'idx_b' => 0,
        ]);
        $resA->assertOk();
        $this->assertEquals('15', $resA->json('saved.point_display_a'));

        // Host B juga menambah poin A dengan event_id berbeda sebelum polling
        $resB = $this->actingAs($scorerUser)->postJson(route('scoring.update-score'), [
            'game_id' => $session->session_id,
            'round' => 'round_1',
            'match_key' => 'round_1_court_1',
            'court' => 1,
            'client_id' => 'client_host_B',
            'client_seq' => 1,
            'event_id' => 'evt_b_unique_2',
            'action' => 'add_point',
            'point_won_by' => 'A',
            'base_version' => 0, // Concurrent divergence!
            'score_a' => 0,
            'score_b' => 0,
            'point_display_a' => '15',
            'point_display_b' => '0',
            'idx_a' => 1,
            'idx_b' => 0,
        ]);

        $resB->assertOk()
            ->assertJson([
                'success' => true,
                'merged' => true,
                'version' => 2,
            ]);

        // State sekarang harus 30 - 0 (karena 15 + 15)
        $this->assertEquals('30', $resB->json('saved.point_display_a'));
        $this->assertEquals('0', $resB->json('saved.point_display_b'));
    }

    /**
     * Test D: Duplicate Request - Event ID yang sama dikirim dua kali hanya dihitung satu kali
     */
    public function test_duplicate_event_id_is_ignored_and_not_counted_twice(): void
    {
        [$session, $hostUser] = $this->createTestSession(1);

        $payload = [
            'game_id' => $session->session_id,
            'round' => 'round_1',
            'match_key' => 'round_1_court_1',
            'court' => 1,
            'client_id' => 'client_host_A',
            'client_seq' => 1,
            'event_id' => 'evt_unique_retry_123',
            'action' => 'add_point',
            'point_won_by' => 'A',
            'base_version' => 0,
            'score_a' => 0,
            'score_b' => 0,
            'point_display_a' => '15',
            'point_display_b' => '0',
            'idx_a' => 1,
            'idx_b' => 0,
        ];

        // First attempt: success
        $res1 = $this->actingAs($hostUser)->postJson(route('scoring.update-score'), $payload);
        $res1->assertOk()
            ->assertJson([
                'success' => true,
                'version' => 1,
                'stale_ignored' => false,
            ]);
        $this->assertFalse($res1->json('duplicate') ?? false);

        // Second attempt (retry of exact same event_id): duplicate detected
        $res2 = $this->actingAs($hostUser)->postJson(route('scoring.update-score'), $payload);
        $res2->assertOk()
            ->assertJson([
                'success' => true,
                'duplicate' => true,
                'version' => 1, // Version tidak bertambah
                'stale_ignored' => false,
            ]);

        $this->assertEquals('15', $res2->json('saved.point_display_a'));
    }

    /**
     * Test E: Per-client out-of-order sequence (stale request dari client yang sama diabaikan)
     */
    public function test_stale_client_sequence_from_same_client_is_ignored(): void
    {
        [$session, $hostUser] = $this->createTestSession(1);

        // Request 1: seq 5
        $this->actingAs($hostUser)->postJson(route('scoring.update-score'), [
            'game_id' => $session->session_id,
            'round' => 'round_1',
            'match_key' => 'round_1_court_1',
            'court' => 1,
            'client_id' => 'client_seq_test',
            'client_seq' => 5,
            'event_id' => 'evt_seq_5',
            'action' => 'add_point',
            'point_won_by' => 'A',
            'base_version' => 0,
            'score_a' => 0,
            'score_b' => 0,
            'point_display_a' => '15',
            'point_display_b' => '0',
            'idx_a' => 1,
            'idx_b' => 0,
        ]);

        // Request 2 (stale/delayed packet): seq 3 dari client yang sama
        $resStale = $this->actingAs($hostUser)->postJson(route('scoring.update-score'), [
            'game_id' => $session->session_id,
            'round' => 'round_1',
            'match_key' => 'round_1_court_1',
            'court' => 1,
            'client_id' => 'client_seq_test',
            'client_seq' => 3,
            'event_id' => 'evt_seq_3_stale',
            'action' => 'add_point',
            'point_won_by' => 'B',
            'base_version' => 0,
            'score_a' => 0,
            'score_b' => 0,
            'point_display_a' => '0',
            'point_display_b' => '15',
            'idx_a' => 0,
            'idx_b' => 1,
        ]);

        $resStale->assertOk()
            ->assertJson([
                'success' => true,
                'stale_ignored' => true,
            ]);

        // Score A tetap 15, Point B tidak masuk
        $this->assertEquals('15', $resStale->json('saved.point_display_a'));
        $this->assertEquals('0', $resStale->json('saved.point_display_b'));
    }

    /**
     * Test F: Polling 0.8s Endpoint mengembalikan state dan version terbaru
     */
    public function test_polling_endpoint_returns_current_version_and_state(): void
    {
        [$session, $hostUser] = $this->createTestSession(1);

        $cacheKey = "scoring.game_{$session->session_id}";
        Cache::put($cacheKey, [
            'round_1_court_1' => [
                'version' => 7,
                'status' => 'in_progress',
                'score_a' => 4,
                'score_b' => 2,
                'point_display_a' => '30',
                'point_display_b' => '15',
                'games_a' => 4,
                'games_b' => 2,
                'idx_a' => 2,
                'idx_b' => 1,
            ],
            '_meta' => [
                'active_round' => 'round_1',
            ],
        ], now()->addHours(1));

        $res = $this->getJson(route('scoring.get-score', [
            'gameId' => $session->session_id,
            'round' => 'round_1',
            'court' => 0,
            'match_key' => 'round_1_court_1',
        ]));

        $res->assertOk()
            ->assertJson([
                'game_id' => $session->session_id,
                'round' => 'round_1',
                'version' => 7,
                'score_a' => 4,
                'score_b' => 2,
                'point_display_a' => '30',
                'point_display_b' => '15',
                'status' => 'in_progress',
            ]);
    }

    /**
     * Test G: Completion & Next Round Barrier
     * Next Round ditolak jika match masih in_progress, dan diizinkan jika sudah completed.
     */
    public function test_next_round_barrier_with_multi_court_completion(): void
    {
        [$session, $hostUser] = $this->createTestSession(2);

        $cacheKey = "scoring.game_{$session->session_id}";

        // 1. Court 1 completed, Court 2 masih in_progress -> DITOLAK (302 redirect dengan error)
        Cache::put($cacheKey, [
            'round_1_court_1' => [
                'status' => 'completed',
                'games_a' => 6,
                'games_b' => 3,
            ],
            'round_1_court_2' => [
                'status' => 'in_progress',
                'games_a' => 4,
                'games_b' => 5,
            ],
            '_meta' => [
                'active_round' => 'round_1',
            ],
        ], now()->addHours(1));

        $resBlocked = $this->actingAs($hostUser)->post(route('scoring.next-round'), [
            'game_id' => $session->session_id,
            'current_round' => 'round_1',
            'next_round' => 'round_2',
        ]);
        $resBlocked->assertSessionHas('warning');

        // 2. Sekarang kedua court sudah completed -> DITERIMA (302 redirect ke round_2)
        Cache::put($cacheKey, [
            'round_1_court_1' => [
                'status' => 'completed',
                'games_a' => 6,
                'games_b' => 3,
            ],
            'round_1_court_2' => [
                'status' => 'completed',
                'games_a' => 6,
                'games_b' => 5,
            ],
            '_meta' => [
                'active_round' => 'round_1',
            ],
        ], now()->addHours(1));

        $resAllowed = $this->actingAs($hostUser)->post(route('scoring.next-round'), [
            'game_id' => $session->session_id,
            'current_round' => 'round_1',
            'next_round' => 'round_2',
        ]);
        $resAllowed->assertRedirect(route('scoring.live', [
            'id' => $session->session_id,
            'format' => 'americano',
            'round' => 'round_2',
            'court' => 0,
        ]));
        $resAllowed->assertSessionHas('success');
    }

    /**
     * Test H: Server menghitung score berdasarkan state database/cache, BUKAN snapshot client (Requirement 1)
     * Contoh: Client mengirim score_a: 99 (snapshot salah/outdated), tetapi server menghitung dari state 5-4 -> 6-4
     * Menggunakan "First to 8" agar setiap add_point langsung menambah 1 games (scoring model Americano/Padel).
     */
    public function test_server_computes_score_from_event_without_relying_on_client_snapshot(): void
    {
        [$session, $hostUser] = $this->createTestSession(1);

        // Update sesi ke scoring system "First to 8" agar setiap add_point = +1 games
        $session->update(['scoring_system' => 'First to 8']);

        // Pre-populate server state: 5 - 4 (games_a = 5, games_b = 4, version = 5)
        // Dengan First to 8, idx tidak digunakan; setiap +A langsung menambah gamesA.
        $cacheKey = "scoring.game_{$session->session_id}";
        Cache::put($cacheKey, [
            'round_1_court_1' => [
                'version' => 5,
                'status' => 'in_progress',
                'score_a' => 5,
                'score_b' => 4,
                'games_a' => 5,
                'games_b' => 4,
                'idx_a' => 0,
                'idx_b' => 0,
                'point_display_a' => '5',
                'point_display_b' => '4',
            ],
            '_meta' => [
                'active_round' => 'round_1',
            ],
        ], now()->addHours(1));

        // Client sengaja mengirim snapshot ngawur: score_a: 99, score_b: 88
        $res = $this->actingAs($hostUser)->postJson(route('scoring.update-score'), [
            'game_id' => $session->session_id,
            'round' => 'round_1',
            'match_key' => 'round_1_court_1',
            'court' => 1,
            'client_id' => 'client_host_1',
            'client_seq' => 10,
            'event_id' => 'evt_uuid_calc_test_999',
            'action' => 'add_point',
            'point_won_by' => 'A',
            'base_version' => 5,
            'score_a' => 99, // Snapshot ngawur/outdated
            'score_b' => 88,
            'games_a' => 99,
            'games_b' => 88,
        ]);

        $res->assertOk();
        $this->assertEquals(6, $res->json('version'));
        $this->assertEquals('evt_uuid_calc_test_999', $res->json('last_event_id'));
        // Server HARUS mengabaikan 99 dan menghitung 5 + 1 = 6
        $this->assertEquals(6, $res->json('saved.score_a'));
        $this->assertEquals(4, $res->json('saved.score_b'));
    }

    /**
     * Test I: Multi-Host Concurrency 5-4 -> Host A +A, Host B +B -> Hasil Wajib 6-5 (Requirement 3)
     * Menggunakan "First to 8" agar setiap add_point langsung +1 games (tanpa tennis point ladder).
     * Kedua event masuk secara hampir bersamaan tanpa saling menimpa.
     */
    public function test_two_hosts_concurrent_scoring_5_4_to_6_5_without_overwriting(): void
    {
        [$session, $hostUser, $scorerUser] = $this->createTestSession(1);

        // Update sesi ke scoring system "First to 8" agar setiap add_point = +1 games
        $session->update(['scoring_system' => 'First to 8']);

        // State awal di server: 5 - 4 (version 10)
        $cacheKey = "scoring.game_{$session->session_id}";
        Cache::put($cacheKey, [
            'round_1_court_1' => [
                'version' => 10,
                'status' => 'in_progress',
                'score_a' => 5,
                'score_b' => 4,
                'games_a' => 5,
                'games_b' => 4,
                'idx_a' => 0,
                'idx_b' => 0,
                'point_display_a' => '5',
                'point_display_b' => '4',
            ],
            '_meta' => [
                'active_round' => 'round_1',
            ],
        ], now()->addHours(1));

        // 1. Host A mengirim +A (base_version = 10)
        $resA = $this->actingAs($hostUser)->postJson(route('scoring.update-score'), [
            'game_id' => $session->session_id,
            'round' => 'round_1',
            'match_key' => 'round_1_court_1',
            'court' => 1,
            'client_id' => 'client_host_A',
            'client_seq' => 1,
            'event_id' => 'evt_host_a_point_a',
            'action' => 'add_point',
            'point_won_by' => 'A',
            'base_version' => 10,
            'score_a' => 5,
            'score_b' => 4,
        ]);

        $resA->assertOk();
        $this->assertEquals(11, $resA->json('version'));
        $this->assertEquals(6, $resA->json('saved.score_a'));
        $this->assertEquals(4, $resA->json('saved.score_b'));

        // 2. Host B mengirim +B (belum menerima update Host A, melihat base_version = 10)
        $resB = $this->actingAs($scorerUser)->postJson(route('scoring.update-score'), [
            'game_id' => $session->session_id,
            'round' => 'round_1',
            'match_key' => 'round_1_court_1',
            'court' => 1,
            'client_id' => 'client_host_B',
            'client_seq' => 1,
            'event_id' => 'evt_host_b_point_b',
            'action' => 'add_point',
            'point_won_by' => 'B',
            'base_version' => 10, // Concurrent divergence!
            'score_a' => 5,
            'score_b' => 4,
        ]);

        $resB->assertOk();
        // HASIL WAJIB: 6 - 5, bukan salah satu event menimpa event lainnya!
        $this->assertEquals(12, $resB->json('version'));
        $this->assertEquals(6, $resB->json('saved.score_a'));
        $this->assertEquals(5, $resB->json('saved.score_b'));
    }

    /**
     * Test J: Stale snapshot dari client lama tidak boleh menurunkan skor final yang sudah selesai.
     */
    public function test_completed_final_score_is_not_overwritten_by_stale_snapshot_when_cache_is_empty(): void
    {
        [$session, $hostUser] = $this->createTestSession(1);

        $drawingId = DB::table('tb_drawing')->insertGetId([
            'session_id' => $session->session_id,
            'match_format_id' => null,
            'status_drawing' => 'Locked',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $matchId = DB::table('tb_match')->insertGetId([
            'drawing_id' => $drawingId,
            'court_id' => 1,
            'nomor_match' => 1,
            'status_match' => 'Completed',
            'waktu_mulai' => now(),
            'waktu_selesai' => now(),
            'hasil_pertandingan' => 'Set Score 6 - 4',
            'winner_team' => 'Team A',
            'version' => 9,
            'last_event_id' => 'evt_final_6_4',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('tb_score')->insert([
            'match_id' => $matchId,
            'set_number' => 1,
            'game_number' => 1,
            'point_score_a' => '40',
            'point_score_b' => '15',
            'game_score_a' => 6,
            'game_score_b' => 4,
            'set_score_a' => 1,
            'set_score_b' => 0,
            'score_side_a' => 6,
            'score_side_b' => 4,
            'scoring_system' => 'Total of 3',
            'status_score' => 'Final',
            'version' => 9,
            'last_event_id' => 'evt_final_6_4',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cacheKey = "scoring.game_{$session->session_id}";
        Cache::forget($cacheKey);

        $res = $this->actingAs($hostUser)->postJson(route('scoring.update-score'), [
            'game_id' => $session->session_id,
            'round' => 'round_1',
            'match_key' => 'round_1_court_1',
            'court' => 1,
            'score_a' => 5,
            'score_b' => 4,
            'point_display_a' => '40',
            'point_display_b' => '15',
            'games_a' => 5,
            'games_b' => 4,
            'sets_a' => 1,
            'sets_b' => 0,
            'set_number' => 1,
            'status' => 'completed',
            'winner_team' => 'Team A',
            'client_id' => 'stale_client',
            'client_seq' => 1,
            'event_id' => 'evt_stale_snapshot_5_4',
            'base_version' => 0,
            'scoring_type' => 'total_of_sets',
        ]);

        $res->assertOk();
        $this->assertSame(6, $res->json('saved.games_a'));
        $this->assertSame(4, $res->json('saved.games_b'));
        $this->assertSame('completed', $res->json('saved.status'));
    }

    /**
     * Test J: Database Persistence Atomic dengan version dan last_event_id (Requirement 7)
     */
    public function test_atomic_database_persistence_with_version_and_last_event_id(): void
    {
        [$session, $hostUser] = $this->createTestSession(1);

        // Buat drawing, match, dan score di DB
        $drawingId = DB::table('tb_drawing')->insertGetId([
            'session_id' => $session->session_id,
            'match_format_id' => null,
            'status_drawing' => 'Locked',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $matchId = DB::table('tb_match')->insertGetId([
            'drawing_id' => $drawingId,
            'nomor_match' => 1,
            'status_match' => 'In Progress',
            'version' => 0,
            'last_event_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $scoreId = DB::table('tb_score')->insertGetId([
            'match_id' => $matchId,
            'set_number' => 1,
            'game_number' => 1,
            'score_side_a' => 0,
            'score_side_b' => 0,
            'version' => 0,
            'last_event_id' => null,
            'status_score' => 'In Progress',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $eventId = 'evt_atomic_db_uuid_'.uniqid();

        $res = $this->actingAs($hostUser)->postJson(route('scoring.update-score'), [
            'game_id' => $session->session_id,
            'round' => 'round_1',
            'match_key' => 'round_1_court_1',
            'court' => 1,
            'client_id' => 'client_db_tester',
            'client_seq' => 1,
            'event_id' => $eventId,
            'action' => 'add_point',
            'point_won_by' => 'A',
            'base_version' => 0,
        ]);

        $res->assertOk();
        $this->assertEquals(1, $res->json('version'));
        $this->assertEquals($eventId, $res->json('last_event_id'));

        // Cek langsung ke database tb_score
        $dbScore = DB::table('tb_score')->where('score_id', $scoreId)->first();
        $this->assertNotNull($dbScore);
        $this->assertEquals(1, $dbScore->version);
        $this->assertEquals($eventId, $dbScore->last_event_id);

        // Cek langsung ke database tb_match
        $dbMatch = DB::table('tb_match')->where('match_id', $matchId)->first();
        $this->assertNotNull($dbMatch);
        $this->assertEquals(1, $dbMatch->version);
        $this->assertEquals($eventId, $dbMatch->last_event_id);
    }
}
