<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\Drawing;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\SessionModel;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ScoringNextRoundTest extends TestCase
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
                $table->timestamps();
            });
        }
    }

    protected function createTestSession($courtCount = 2)
    {
        $hostUser = User::create([
            'nama' => 'Host User',
            'email' => 'host_'.uniqid().'@matcha.com',
            'role' => 'host',
            'password' => bcrypt('secret'),
        ]);

        $sport = Sport::firstOrCreate(['nama_sport' => 'Padel'], ['status_sport' => 'active']);
        $venue = Venue::create(['nama_venue' => 'Arena Test']);

        $session = SessionModel::create([
            'host_user_id' => $hostUser->user_id,
            'sport_id' => $sport->sport_id,
            'venue_id' => $venue->venue_id,
            'nama_session' => 'Mabar Test Multi Court',
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
            'nama' => 'Host Player',
            'gender' => 'Male',
            'level' => 'Intermediate',
        ]);
        DB::table('tb_session_player')->insert([
            'session_id' => $session->session_id,
            'player_id' => $hostPlayer->player_id,
        ]);

        $memberUsers = [];
        for ($p = 2; $p <= 8; $p++) {
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
            $memberUsers[] = ['user' => $mUser, 'player' => $player];
        }

        return [$session, $hostUser, $hostPlayer, $memberUsers];
    }

    /**
     * Test 1: Non-Host (Member atau Guest) dilarang mengakses endpoint /scoring/next-round
     */
    public function test_only_host_can_access_next_round_endpoint(): void
    {
        [$session, $hostUser, $hostPlayer, $memberUsers] = $this->createTestSession(2);
        $memberUser = $memberUsers[0]['user'];

        // 1. Guest dilarang (403)
        $resGuest = $this->post(route('scoring.next-round'), [
            'game_id' => $session->session_id,
            'current_round' => 'round_1',
            'next_round' => 'round_2',
        ]);
        $resGuest->assertForbidden();

        // 2. Member biasa dilarang (403)
        $resMember = $this->actingAs($memberUser)->post(route('scoring.next-round'), [
            'game_id' => $session->session_id,
            'current_round' => 'round_1',
            'next_round' => 'round_2',
        ]);
        $resMember->assertForbidden();
    }

    /**
     * Test 2: Host tidak dapat lanjut ke ronde berikutnya jika masih ada court yang bermain
     */
    public function test_host_cannot_advance_if_court_is_still_playing(): void
    {
        [$session, $hostUser] = $this->createTestSession(2);

        // Simulasi: Court 1 completed, Court 2 masih in_progress
        $cacheKey = "scoring.game_{$session->session_id}";
        Cache::put($cacheKey, [
            'round_1_court_1' => [
                'status' => 'completed',
                'games_a' => 6,
                'games_b' => 2,
            ],
            'round_1_court_2' => [
                'status' => 'in_progress',
                'games_a' => 3,
                'games_b' => 2,
            ],
            '_meta' => [
                'active_round' => 'round_1',
            ],
        ], now()->addHours(1));

        $res = $this->actingAs($hostUser)->post(route('scoring.next-round'), [
            'game_id' => $session->session_id,
            'current_round' => 'round_1',
            'next_round' => 'round_2',
        ]);

        $res->assertRedirect();
        $res->assertSessionHas('warning', 'Set/Ronde saat ini belum selesai. Selesaikan seluruh pertandingan terlebih dahulu.');
    }

    /**
     * Test 3: Host berhasil lanjut ke ronde berikutnya setelah seluruh court selesai
     */
    public function test_host_can_advance_when_all_courts_are_completed(): void
    {
        [$session, $hostUser] = $this->createTestSession(2);

        // Simulasi: Kedua court selesai
        $cacheKey = "scoring.game_{$session->session_id}";
        Cache::put($cacheKey, [
            'round_1_court_1' => [
                'status' => 'completed',
                'games_a' => 6,
                'games_b' => 2,
            ],
            'round_1_court_2' => [
                'status' => 'completed',
                'games_a' => 6,
                'games_b' => 4,
            ],
            '_meta' => [
                'active_round' => 'round_1',
            ],
        ], now()->addHours(1));

        $res = $this->actingAs($hostUser)->post(route('scoring.next-round'), [
            'game_id' => $session->session_id,
            'current_round' => 'round_1',
            'next_round' => 'round_2',
        ]);

        $res->assertRedirect();
        $res->assertSessionHas('success');

        // Pastikan active_round di cache terupdate ke round_2
        $updatedCache = Cache::get($cacheKey);
        $this->assertEquals('round_2', $updatedCache['_meta']['active_round']);
    }

    /**
     * Test 4: Direct URL access ditolak jika ronde sebelumnya belum selesai
     */
    public function test_direct_url_bypass_is_rejected_when_round_not_completed(): void
    {
        [$session, $hostUser] = $this->createTestSession(2);

        // Cache masih kosong / in_progress di Set 1
        $cacheKey = "scoring.game_{$session->session_id}";
        Cache::put($cacheKey, [
            'round_1_court_1' => [
                'status' => 'in_progress',
                'games_a' => 2,
                'games_b' => 1,
            ],
        ], now()->addHours(1));

        // Mencoba bypass membuka ?round=2 secara manual
        $res = $this->actingAs($hostUser)->get(route('scoring.live', [
            'id' => $session->session_id,
            'round' => 2,
        ]));

        $res->assertRedirect(route('scoring.live', [
            'id' => $session->session_id,
            'format' => 'americano',
            'round' => 'round_1',
            'court' => 0,
        ]));
        $res->assertSessionHas('warning', 'Set/Ronde saat ini belum selesai. Selesaikan seluruh pertandingan terlebih dahulu.');
    }

    /**
     * Test 5: Non-host tidak dapat membuka ronde berikutnya jika belum dibuka oleh Host
     */
    public function test_non_host_cannot_jump_to_unstarted_round(): void
    {
        [$session, $hostUser, $hostPlayer, $memberUsers] = $this->createTestSession(2);
        $memberUser = $memberUsers[0]['user'];

        // Set 1 sudah completed, tapi Host belum memajukan active_round ke round_2
        $cacheKey = "scoring.game_{$session->session_id}";
        Cache::put($cacheKey, [
            'round_1_court_1' => [
                'status' => 'completed',
                'games_a' => 6,
                'games_b' => 4,
            ],
            'round_1_court_2' => [
                'status' => 'completed',
                'games_a' => 6,
                'games_b' => 3,
            ],
            '_meta' => [
                'active_round' => 'round_1',
            ],
        ], now()->addHours(1));

        // Member mencoba langsung buka ?round=2
        $res = $this->actingAs($memberUser)->get(route('scoring.live', [
            'id' => $session->session_id,
            'round' => 'round_2',
        ]));

        $res->assertRedirect(route('scoring.live', [
            'id' => $session->session_id,
            'format' => 'americano',
            'round' => 'round_1',
            'court' => 0,
        ]));
        $res->assertSessionHas('warning', 'Hanya Host yang dapat melanjutkan ke set/ronde berikutnya.');
    }
}
