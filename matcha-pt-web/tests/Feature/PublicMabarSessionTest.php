<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\SessionModel;
use App\Models\Sport;
use App\Models\Venue;
use App\Models\Court;
use App\Models\Player;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class PublicMabarSessionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setupTestDatabaseSchema();
    }

    protected function setupTestDatabaseSchema(): void
    {
        if (!Schema::hasTable('tb_user')) {
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

        if (!Schema::hasTable('tb_sport')) {
            Schema::create('tb_sport', function ($table) {
                $table->id('sport_id');
                $table->string('nama_sport');
                $table->string('status_sport')->default('active');
            });
        }

        if (!Schema::hasTable('tb_venue')) {
            Schema::create('tb_venue', function ($table) {
                $table->id('venue_id');
                $table->unsignedBigInteger('owner_user_id')->nullable();
                $table->string('nama_venue');
                $table->string('alamat')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('tb_court')) {
            Schema::create('tb_court', function ($table) {
                $table->id('court_id');
                $table->string('nama_court');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('tb_session')) {
            Schema::create('tb_session', function ($table) {
                $table->id('session_id');
                $table->unsignedBigInteger('host_user_id')->nullable();
                $table->unsignedBigInteger('sport_id')->nullable();
                $table->unsignedBigInteger('venue_id')->nullable();
                $table->string('nama_session')->default('Session Test');
                $table->string('scoring_system')->default('Total of 3');
                $table->string('waktu_session')->nullable();
                $table->dateTime('datetime')->nullable();
                $table->string('status_session')->default('Open');
                $table->integer('jumlah_pemain')->default(4);
                $table->string('jenis_permainan')->default('Double');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('tb_session_court')) {
            Schema::create('tb_session_court', function ($table) {
                $table->unsignedBigInteger('session_id');
                $table->unsignedBigInteger('court_id');
            });
        }

        if (!Schema::hasTable('tb_player')) {
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

        if (!Schema::hasTable('tb_session_player')) {
            Schema::create('tb_session_player', function ($table) {
                $table->unsignedBigInteger('session_id');
                $table->unsignedBigInteger('player_id');
            });
        }

        if (!Schema::hasTable('tb_match_format')) {
            Schema::create('tb_match_format', function ($table) {
                $table->id('match_format_id');
                $table->string('nama_format');
                $table->string('deskripsi')->nullable();
            });
        }

        if (!Schema::hasTable('tb_drawing')) {
            Schema::create('tb_drawing', function ($table) {
                $table->id('drawing_id');
                $table->unsignedBigInteger('session_id');
                $table->unsignedBigInteger('match_format_id')->default(1);
                $table->date('tanggal_drawing')->nullable();
                $table->time('jam_drawing')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Helper untuk membuat sesi mabar valid.
     */
    protected function createValidSession(string $title = 'Mabar Padel Eksklusif'): SessionModel
    {
        $user = User::create([
            'nama' => 'Host Budi',
            'email' => 'host_' . uniqid() . '@matcha.com',
            'role' => 'host',
            'password' => bcrypt('secret'),
        ]);

        $sport = Sport::firstOrCreate(['nama_sport' => 'Padel'], ['status_sport' => 'active']);
        $venue = Venue::create(['nama_venue' => 'Cilandak Padel Club', 'owner_user_id' => $user->user_id]);
        $court = Court::create(['nama_court' => 'Court A']);

        $session = SessionModel::create([
            'host_user_id' => $user->user_id,
            'sport_id' => $sport->sport_id,
            'venue_id' => $venue->venue_id,
            'nama_session' => $title,
            'scoring_system' => 'Total of 3',
            'waktu_session' => '19:00 WIB',
            'datetime' => now(),
            'status_session' => 'Open',
            'jumlah_pemain' => 6,
            'jenis_permainan' => 'Double',
        ]);

        DB::table('tb_session_court')->insert([
            'session_id' => $session->session_id,
            'court_id' => $court->court_id,
        ]);

        return $session;
    }

    /**
     * Test A: Sesi valid -> HTTP 200 dan detail session benar.
     */
    public function test_bug_pub_001_valid_session_returns_200_and_correct_details(): void
    {
        $session = $this->createValidSession('Mabar Padel Sore Seru');

        $response = $this->get('/games/' . $session->session_id);

        $response->assertStatus(200);
        $response->assertSee('Mabar Padel Sore Seru');
        $response->assertSee('Cilandak Padel Club');
    }

    /**
     * Test B: Non-existent session -> HTTP 404.
     */
    public function test_bug_pub_001_non_existent_session_returns_404(): void
    {
        $response = $this->get('/games/999999');

        $response->assertStatus(404);
    }

    /**
     * Test C: ID 0 -> HTTP 404.
     */
    public function test_bug_pub_001_zero_id_returns_404(): void
    {
        $response = $this->get('/games/0');

        $response->assertStatus(404);
    }

    /**
     * Test D: Respon 404 TIDAK membocorkan data sesi valid lain atau dummy data fallback.
     */
    public function test_bug_pub_001_404_does_not_contain_valid_or_dummy_session_data(): void
    {
        $session = $this->createValidSession('Sesi Rahasia Yang Tidak Boleh Bocor');

        $response = $this->get('/games/888888');

        $response->assertStatus(404);
        // Pastikan tidak ada data sesi valid yang bocor
        $response->assertDontSee('Sesi Rahasia Yang Tidak Boleh Bocor');
        // Pastikan fallback lama ke dummy session [0] tidak terjadi
        $response->assertDontSee('Mabar Padel JTK Bonang');
    }

    /**
     * Test E: ID negatif dan non-numeric string -> HTTP 404 (route numeric constraint).
     */
    public function test_bug_pub_001_invalid_format_id_returns_404(): void
    {
        $response1 = $this->get('/games/-1');
        $response1->assertStatus(404);

        $response2 = $this->get('/games/abc');
        $response2->assertStatus(404);
    }
}
