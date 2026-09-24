<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\SessionModel;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicMabarSessionTest extends TestCase
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
                $table->boolean('is_host')->default(false);
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
                $table->string('waktu_session')->nullable();
                $table->dateTime('datetime')->nullable();
                $table->string('status_session')->default('Open');
                $table->integer('jumlah_pemain')->default(4);
                $table->string('jenis_permainan')->default('Double');
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

        if (! Schema::hasTable('tb_match_format')) {
            Schema::create('tb_match_format', function ($table) {
                $table->id('match_format_id');
                $table->string('nama_format');
                $table->string('deskripsi')->nullable();
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

        if (! Schema::hasTable('tb_kudos')) {
            Schema::create('tb_kudos', function ($table) {
                $table->id('kudos_id');
                $table->unsignedBigInteger('session_id');
                $table->unsignedBigInteger('giver_user_id')->nullable();
                $table->unsignedBigInteger('recipient_player_id')->nullable();
                $table->string('recipient_name');
                $table->string('badge');
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
            'email' => 'host_'.uniqid().'@matcha.com',
            'role' => 'host',
            'is_host' => true,
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

        $response = $this->get('/games/'.$session->session_id);

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

    public function test_guest_can_view_drawing_but_cannot_shuffle_or_lock_it(): void
    {
        $session = $this->createValidSession();

        // Saat sesi masih Open dan belum drawing, guest/non-host dilarang akses
        $this->get('/games/'.$session->session_id.'/drawing')
            ->assertRedirect('/games/'.$session->session_id)
            ->assertSessionHas('error');

        // Setelah sesi berstatus In Progress (sudah drawing), guest/non-host bisa melihat drawing (view-only)
        $session->update(['status_session' => 'In Progress']);

        $this->get('/games/'.$session->session_id.'/drawing')->assertOk();
        $this->getJson('/games/'.$session->session_id.'/drawing?shuffle=1&seed=123')->assertUnauthorized();
        $this->postJson('/games/'.$session->session_id.'/lock')->assertUnauthorized();
    }

    public function test_invalid_public_drawing_live_score_and_recap_ids_return_404(): void
    {
        $this->get('/games/999999/drawing')->assertNotFound();
        $this->get('/scoring/live/999999')->assertNotFound();
        $this->get('/scoring/get-score/999999/round_1')->assertNotFound();
        $this->get('/scoring/recap/999999')->assertNotFound();
    }

    public function test_public_recap_is_available_without_login_for_valid_session(): void
    {
        $session = $this->createValidSession('Recap Publik');

        $this->get('/scoring/recap/'.$session->session_id)
            ->assertOk()
            ->assertSee('Recap Publik');
    }

    public function test_guest_cannot_update_score_or_finish_session(): void
    {
        $session = $this->createValidSession();

        $this->postJson('/scoring/update-score', [
            'game_id' => $session->session_id,
            'round' => 'round_1',
            'score_a' => 1,
            'score_b' => 0,
        ])->assertUnauthorized();

        $this->post('/scoring/finish', [
            'game_id' => $session->session_id,
            'round' => 'round_1',
        ])->assertRedirect();
    }

    public function test_session_host_can_still_lock_own_drawing(): void
    {
        $session = $this->createValidSession();
        $host = User::findOrFail($session->host_user_id);

        $this->actingAs($host)
            ->post('/games/'.$session->session_id.'/lock')
            ->assertRedirectToRoute('scoring.live', ['id' => $session->session_id, 'format' => 'Americano']);

        $this->assertDatabaseHas('tb_session', [
            'session_id' => $session->session_id,
            'status_session' => 'In Progress',
        ]);
    }

    public function test_host_cannot_mutate_another_hosts_session(): void
    {
        $ownedSession = $this->createValidSession();
        $otherSession = $this->createValidSession();
        $owner = User::findOrFail($ownedSession->host_user_id);

        $this->actingAs($owner)
            ->post('/games/'.$otherSession->session_id.'/lock')
            ->assertForbidden();

        $this->actingAs($owner)
            ->postJson('/scoring/update-score', [
                'game_id' => $otherSession->session_id,
                'round' => 'round_1',
                'score_a' => 1,
                'score_b' => 0,
            ])
            ->assertForbidden();

        $this->actingAs($owner)
            ->post('/scoring/finish', [
                'game_id' => $otherSession->session_id,
                'round' => 'round_1',
            ])
            ->assertForbidden();
    }

    public function test_admin_can_delete_any_mabar_session(): void
    {
        $session = $this->createValidSession();
        $admin = User::create([
            'nama' => 'Super Admin',
            'email' => 'admin@matcha.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)
            ->delete('/games/'.$session->session_id);

        $response->assertRedirect(route('games.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('tb_session', [
            'session_id' => $session->session_id,
        ]);
    }

    public function test_host_cannot_delete_own_mabar_session(): void
    {
        $session = $this->createValidSession();
        $host = User::findOrFail($session->host_user_id);

        $response = $this->actingAs($host)
            ->delete('/games/'.$session->session_id);

        $response->assertRedirect(route('games.show', $session->session_id));
        $response->assertSessionHas('error');

        // Sesi mabar masih tetap ada di DB
        $this->assertDatabaseHas('tb_session', [
            'session_id' => $session->session_id,
        ]);
    }

    public function test_non_host_member_cannot_delete_session(): void
    {
        $session = $this->createValidSession();
        $otherMember = User::create([
            'nama' => 'Other Member',
            'email' => 'othermember@matcha.test',
            'password' => bcrypt('password'),
            'role' => 'member',
        ]);

        $response = $this->actingAs($otherMember)
            ->delete('/games/'.$session->session_id);

        $response->assertRedirect(route('games.show', $session->session_id));
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('tb_session', [
            'session_id' => $session->session_id,
        ]);
    }

    public function test_guest_cannot_delete_session(): void
    {
        $session = $this->createValidSession();

        $response = $this->delete('/games/'.$session->session_id);

        $response->assertRedirect('/login');
        $this->assertDatabaseHas('tb_session', [
            'session_id' => $session->session_id,
        ]);
    }

    public function test_delete_button_only_visible_to_admin_on_show_page(): void
    {
        $session = $this->createValidSession();
        $host = User::findOrFail($session->host_user_id);
        $member = User::create([
            'nama' => 'Pemain Member',
            'email' => 'memberplay@matcha.test',
            'password' => bcrypt('password'),
            'role' => 'member',
        ]);
        $admin = User::create([
            'nama' => 'Platform Admin',
            'email' => 'platformadmin@matcha.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        // 1. Guest tidak melihat tombol Hapus
        $this->get('/games/'.$session->session_id)
            ->assertDontSee('Hapus Jadwal Mabar');

        // 2. Member biasa tidak melihat tombol Hapus
        $this->actingAs($member)
            ->get('/games/'.$session->session_id)
            ->assertDontSee('Hapus Jadwal Mabar');

        // 3. Host game tidak melihat tombol Hapus
        $this->actingAs($host)
            ->get('/games/'.$session->session_id)
            ->assertDontSee('Hapus Jadwal Mabar');

        // 4. Admin platform melihat tombol Hapus jika sesi belum mulai
        $this->actingAs($admin)
            ->get('/games/'.$session->session_id)
            ->assertSee('Hapus Jadwal Mabar');
    }

    public function test_admin_cannot_delete_started_or_finished_session(): void
    {
        $admin = User::create([
            'nama' => 'Admin抹茶',
            'email' => 'adminmatcha_started@matcha.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        // Sesi yang sedang berjalan
        $ongoingSession = $this->createValidSession('Sesi Berjalan');
        $ongoingSession->update(['status_session' => 'In Progress']);

        $responseOngoing = $this->actingAs($admin)->delete('/games/'.$ongoingSession->session_id);
        $responseOngoing->assertRedirect(route('games.show', $ongoingSession->session_id));
        $responseOngoing->assertSessionHas('error');
        $this->assertDatabaseHas('tb_session', ['session_id' => $ongoingSession->session_id]);

        // Sesi yang sudah selesai
        $finishedSession = $this->createValidSession('Sesi Selesai');
        $finishedSession->update(['status_session' => 'Finished']);

        $responseFinished = $this->actingAs($admin)->delete('/games/'.$finishedSession->session_id);
        $responseFinished->assertRedirect(route('games.show', $finishedSession->session_id));
        $responseFinished->assertSessionHas('error');
        $this->assertDatabaseHas('tb_session', ['session_id' => $finishedSession->session_id]);
    }

    public function test_admin_does_not_see_podium_button_on_finished_session(): void
    {
        $admin = User::create([
            'nama' => 'Admin抹茶',
            'email' => 'adminpodium@matcha.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $finishedSession = $this->createValidSession('Sesi Selesai Mabar');
        $finishedSession->update(['status_session' => 'Finished']);

        // Pada halaman show, admin tidak melihat tombol Buka Hasil Akhir & Podium
        $this->actingAs($admin)
            ->get('/games/'.$finishedSession->session_id)
            ->assertDontSee('Buka Hasil Akhir & Podium');

        // Pada halaman show, admin juga tidak melihat tombol Hapus karena sesi sudah selesai
        $this->actingAs($admin)
            ->get('/games/'.$finishedSession->session_id)
            ->assertDontSee('Hapus Jadwal Mabar');
    }
}
