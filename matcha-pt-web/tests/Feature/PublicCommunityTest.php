<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Community;
use App\Models\Player;
use Illuminate\Support\Facades\Schema;

class PublicCommunityTest extends TestCase
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

        if (!Schema::hasTable('tb_community')) {
            Schema::create('tb_community', function ($table) {
                $table->id('community_id');
                $table->string('nama_community');
                $table->text('deskripsi')->nullable();
                $table->string('logo')->nullable();
                $table->string('sport')->default('Padel');
                $table->string('tagline')->nullable();
                $table->string('kota_homebase')->nullable();
                $table->string('target_level')->nullable();
                $table->string('status_keanggotaan')->nullable();
                $table->string('jadwal_rutin')->nullable();
                $table->string('homebase_venue')->nullable();
                $table->json('benefits')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
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
    }

    protected function createCommunity(array $attributes = []): Community
    {
        return Community::create(array_merge([
            'nama_community'     => 'Matcha Padel Bandung',
            'deskripsi'          => 'Komunitas padel asik dan seru di Bandung.',
            'sport'              => 'padel',
            'kota_homebase'      => 'Bandung',
            'status_keanggotaan' => 'Open',
            'jadwal_rutin'       => 'Setiap Sabtu Pagi',
        ], $attributes));
    }

    /**
     * Test 1: Guest dapat membuka halaman /communities dengan status 200.
     */
    public function test_guest_can_access_communities_index(): void
    {
        $this->createCommunity(['nama_community' => 'Bandung Tennis Club']);

        $response = $this->get('/communities');

        $response->assertStatus(200);
        $response->assertSee('Bandung Tennis Club');
    }

    /**
     * Test 2: Guest melihat tombol "Detail Komunitas" yang mengarah ke route communities.show,
     * bukan tombol "Gabung Komunitas" yang misleading.
     */
    public function test_guest_sees_detail_community_button_pointing_to_show(): void
    {
        $community = $this->createCommunity(['nama_community' => 'Alpha Padel Club']);

        $response = $this->get('/communities');

        $response->assertStatus(200);
        // Memastikan tombol bertuliskan "Detail Komunitas"
        $response->assertSee('Detail Komunitas');
        // Memastikan link mengarah ke detail komunitas
        $response->assertSee(route('communities.show', $community->community_id));
        // Memastikan tidak ada teks "Gabung Komunitas" pada tombol card
        $response->assertDontSee('Gabung Komunitas');
    }

    /**
     * Test 3: Thumbnail dan Judul komunitas juga mengarah ke route communities.show.
     */
    public function test_thumbnail_and_title_link_to_community_detail(): void
    {
        $community = $this->createCommunity(['nama_community' => 'Clickable Community']);

        $response = $this->get('/communities');

        $response->assertStatus(200);
        $detailUrl = route('communities.show', $community->community_id);
        // URL detail harus muncul beberapa kali (thumbnail, title, button)
        $content = $response->getContent();
        $occurrences = substr_count($content, $detailUrl);
        $this->assertGreaterThanOrEqual(3, $occurrences, 'Thumbnail, title, dan button harus mengarah ke route detail.');
    }

    /**
     * Test 4: Member yang sudah bergabung tetap memiliki tombol/link "Detail Komunitas"
     * dan tetap menampilkan badge "Anggota Komunitas".
     */
    public function test_member_still_has_detail_community_button_and_member_badge(): void
    {
        $user = User::create([
            'nama'     => 'Member User',
            'email'    => 'member@example.com',
            'password' => bcrypt('secret'),
            'role'     => 'member',
        ]);

        $community = $this->createCommunity(['nama_community' => 'Member Padel Club']);

        Player::create([
            'user_id'      => $user->user_id,
            'community_id' => $community->community_id,
            'nama'         => 'Member User',
        ]);

        $response = $this->actingAs($user)->get('/communities');

        $response->assertStatus(200);
        // Memastikan badge status anggota tetap tampil
        $response->assertSee('Anggota Komunitas');
        // Memastikan tombol Detail Komunitas tetap ada untuk member
        $response->assertSee('Detail Komunitas');
        $response->assertSee(route('communities.show', $community->community_id));
    }

    /**
     * Test 5: Guest dapat membuka halaman detail komunitas dengan ID valid (HTTP 200).
     */
    public function test_guest_can_view_valid_community_detail(): void
    {
        $community = $this->createCommunity([
            'nama_community' => 'Bandung Racquet Society',
            'deskripsi'      => 'Deskripsi lengkap komunitas raket.',
        ]);

        $response = $this->get('/communities/' . $community->community_id);

        $response->assertStatus(200);
        $response->assertSee('Bandung Racquet Society');
        $response->assertSee('Deskripsi lengkap komunitas raket.');
        // Di halaman detail bagi guest, tampil ajakan login untuk bergabung
        $response->assertSee('Login Terlebih Dahulu');
    }

    /**
     * Test 6: Akses detail komunitas dengan ID yang tidak ditemukan menghasilkan HTTP 404 (tidak fallback ke dummy).
     */
    public function test_non_existent_community_id_returns_404(): void
    {
        $response999 = $this->get('/communities/999999');
        $response999->assertStatus(404);

        $response0 = $this->get('/communities/0');
        $response0->assertStatus(404);
    }

    /**
     * Test 7: POST /communities/{id}/join tetap membutuhkan authentication.
     */
    public function test_join_community_requires_authentication(): void
    {
        $community = $this->createCommunity();

        $response = $this->post('/communities/' . $community->community_id . '/join');

        // Guest harus diredirect ke login
        $response->assertRedirect('/login');
    }

    /**
     * Test 8: Flow Join berhasil ketika dijalankan oleh authenticated user dari halaman detail.
     */
    public function test_authenticated_user_can_join_community_successfully(): void
    {
        $user = User::create([
            'nama'     => 'Player Joinee',
            'email'    => 'joinee@example.com',
            'password' => bcrypt('secret'),
            'role'     => 'member',
        ]);

        $community = $this->createCommunity(['nama_community' => 'Club to Join']);

        // User membuka halaman detail terlebih dahulu
        $detailResponse = $this->actingAs($user)->get('/communities/' . $community->community_id);
        $detailResponse->assertStatus(200);
        $detailResponse->assertSee('Bergabung Sekarang');

        // User menekan aksi Join
        $joinResponse = $this->actingAs($user)->post('/communities/' . $community->community_id . '/join');

        $joinResponse->assertRedirect(route('communities.show', $community->community_id));
        $joinResponse->assertSessionHas('success', 'Berhasil bergabung ke komunitas!');

        // Verifikasi di database bahwa player terdaftar di komunitas
        $this->assertDatabaseHas('tb_player', [
            'user_id'      => $user->user_id,
            'community_id' => $community->community_id,
        ]);
    }
}
