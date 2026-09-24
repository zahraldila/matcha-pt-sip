<?php

namespace Tests\Feature;

use App\Models\Community;
use App\Models\Player;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicCommunityTest extends TestCase
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

        if (! Schema::hasTable('tb_community')) {
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
    }

    protected function createCommunity(array $attributes = []): Community
    {
        return Community::create(array_merge([
            'nama_community' => 'Matcha Padel Bandung',
            'deskripsi' => 'Komunitas padel asik dan seru di Bandung.',
            'sport' => 'padel',
            'kota_homebase' => 'Bandung',
            'status_keanggotaan' => 'Open',
            'jadwal_rutin' => 'Setiap Sabtu Pagi',
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
            'nama' => 'Member User',
            'email' => 'member@example.com',
            'password' => bcrypt('secret'),
            'role' => 'member',
        ]);

        $community = $this->createCommunity(['nama_community' => 'Member Padel Club']);

        Player::create([
            'user_id' => $user->user_id,
            'community_id' => $community->community_id,
            'nama' => 'Member User',
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
            'deskripsi' => 'Deskripsi lengkap komunitas raket.',
        ]);

        $response = $this->get('/communities/'.$community->community_id);

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

        $response = $this->post('/communities/'.$community->community_id.'/join');

        // Guest harus diredirect ke login
        $response->assertRedirect('/login');
    }

    /**
     * Test 8: Flow Join berhasil ketika dijalankan oleh authenticated user dari halaman detail.
     */
    public function test_authenticated_user_can_join_community_successfully(): void
    {
        $user = User::create([
            'nama' => 'Player Joinee',
            'email' => 'joinee@example.com',
            'password' => bcrypt('secret'),
            'role' => 'member',
        ]);

        $community = $this->createCommunity(['nama_community' => 'Club to Join']);

        // User membuka halaman detail terlebih dahulu
        $detailResponse = $this->actingAs($user)->get('/communities/'.$community->community_id);
        $detailResponse->assertStatus(200);
        $detailResponse->assertSee('Bergabung Sekarang');

        // User menekan aksi Join
        $joinResponse = $this->actingAs($user)->post('/communities/'.$community->community_id.'/join');

        $joinResponse->assertRedirect(route('communities.show', $community->community_id));
        $joinResponse->assertSessionHas('success', 'Berhasil bergabung ke komunitas!');

        // Verifikasi di database bahwa player terdaftar di komunitas
        $this->assertDatabaseHas('tb_player', [
            'user_id' => $user->user_id,
            'community_id' => $community->community_id,
        ]);
    }

    /**
     * Test 9 (BUG-COMM-001): Join komunitas dengan ID invalid / tidak ada menghasilkan HTTP 404
     * dan tidak membuat atau mengubah relasi membership di database.
     */
    public function test_join_with_invalid_or_nonexistent_id_returns_404_and_does_not_modify_membership(): void
    {
        $user = User::create([
            'nama' => 'Player Invalid Join',
            'email' => 'invalidjoin@example.com',
            'password' => bcrypt('secret'),
            'role' => 'member',
        ]);

        $player = Player::create([
            'user_id' => $user->user_id,
            'community_id' => null,
            'nama' => 'Player Invalid Join',
        ]);

        // Request join ke ID yang tidak ada
        $response = $this->actingAs($user)->post('/communities/999999/join');
        $response->assertStatus(404);

        // Pastikan membership player tidak berubah
        $player->refresh();
        $this->assertNull($player->community_id);
    }

    /**
     * Test 10 (BUG-COMM-002): Memastikan route Join komunitas terproteksi middleware CSRF dan form menyertakan token CSRF.
     */
    public function test_join_community_without_csrf_is_rejected(): void
    {
        $user = User::create([
            'nama' => 'Player CSRF Test',
            'email' => 'csrftest@example.com',
            'password' => bcrypt('secret'),
            'role' => 'member',
        ]);

        $community = $this->createCommunity(['nama_community' => 'CSRF Guarded Club']);

        // 1. Verifikasi halaman detail menampilkan token CSRF di dalam form join
        $response = $this->actingAs($user)->get('/communities/'.$community->community_id);
        $response->assertStatus(200);
        $response->assertSee('name="_token"', false);

        // 2. Verifikasi route terdaftar dengan middleware group 'web' yang berisi ValidateCsrfToken
        $route = app('router')->getRoutes()->match(
            Request::create('/communities/'.$community->community_id.'/join', 'POST')
        );
        $this->assertContains('web', $route->middleware());
    }

    /**
     * Test 11 (BUG-COMM-003): Mengakses route detail tanpa ID (misal /communities/show atau /communities/detail atau /community)
     * memberikan respons 404 yang jelas.
     */
    public function test_accessing_detail_route_without_id_returns_404(): void
    {
        $responseShow = $this->get('/communities/show');
        $responseShow->assertStatus(404);

        $responseDetail = $this->get('/communities/detail');
        $responseDetail->assertStatus(404);

        $responseCommunity = $this->get('/community');
        $responseCommunity->assertStatus(404);
    }

    /**
     * Test 12 (BUG-COMM-004): User dapat menjadi anggota beberapa komunitas tanpa
     * memindahkan membership yang sudah ada.
     */
    public function test_user_can_join_multiple_communities_without_moving_existing_membership(): void
    {
        $user = User::create([
            'nama' => 'Dual Member Player',
            'email' => 'dualmember@example.com',
            'password' => bcrypt('secret'),
            'role' => 'member',
        ]);

        $communityA = $this->createCommunity(['nama_community' => 'Komunitas Asal']);
        $communityB = $this->createCommunity(['nama_community' => 'Komunitas Tujuan']);

        // User terdaftar di Komunitas A
        $player = Player::create([
            'user_id' => $user->user_id,
            'community_id' => $communityA->community_id,
            'nama' => 'Dual Member Player',
        ]);

        // User bergabung ke Komunitas B tanpa keluar dari Komunitas A
        $response = $this->actingAs($user)->post('/communities/'.$communityB->community_id.'/join');

        $response->assertRedirect(route('communities.show', $communityB->community_id));
        $response->assertSessionHas('success', 'Berhasil bergabung ke komunitas!');

        $this->assertDatabaseHas('tb_player', [
            'user_id' => $user->user_id,
            'community_id' => $communityA->community_id,
        ]);
        $this->assertDatabaseHas('tb_player', [
            'user_id' => $user->user_id,
            'community_id' => $communityB->community_id,
        ]);

        // Join ulang ke Komunitas B tidak membuat membership kedua.
        $duplicateResponse = $this->actingAs($user)->post('/communities/'.$communityB->community_id.'/join');
        $duplicateResponse->assertSessionHas('info', 'Anda sudah menjadi bagian dari komunitas ini.');
        $this->assertSame(1, Player::where('user_id', $user->user_id)
            ->where('community_id', $communityB->community_id)
            ->count());
    }

    /**
     * Test 13: User yang sudah terdaftar di komunitas yang sama mendapatkan notifikasi info saat menekan join lagi.
     */
    public function test_duplicate_join_to_same_community_returns_info_message(): void
    {
        $user = User::create([
            'nama' => 'Same Member Player',
            'email' => 'samemember@example.com',
            'password' => bcrypt('secret'),
            'role' => 'member',
        ]);

        $community = $this->createCommunity(['nama_community' => 'Same Club']);

        Player::create([
            'user_id' => $user->user_id,
            'community_id' => $community->community_id,
            'nama' => 'Same Member Player',
        ]);

        $response = $this->actingAs($user)->post('/communities/'.$community->community_id.'/join');

        $response->assertRedirect(route('communities.show', $community->community_id));
        $response->assertSessionHas('info', 'Anda sudah menjadi bagian dari komunitas ini.');
    }

    /**
     * Test 14: User dapat leave komunitas dengan benar.
     */
    public function test_user_can_leave_community(): void
    {
        $user = User::create([
            'nama' => 'Leaving Player',
            'email' => 'leaving@example.com',
            'password' => bcrypt('secret'),
            'role' => 'member',
        ]);

        $community = $this->createCommunity(['nama_community' => 'Leave Club']);

        $player = Player::create([
            'user_id' => $user->user_id,
            'community_id' => $community->community_id,
            'nama' => 'Leaving Player',
        ]);

        $response = $this->actingAs($user)->post('/communities/'.$community->community_id.'/leave');

        $response->assertRedirect(route('communities.index'));
        $response->assertSessionHas('success');

        $player->refresh();
        $this->assertNull($player->community_id);
    }

    /**
     * Test 15: Pembuat komunitas (creator) dapat menghapus komunitasnya dan keanggotaan pemain di-detach dengan benar.
     */
    public function test_creator_can_delete_community_and_members_are_detached(): void
    {
        $creator = User::create([
            'nama' => 'Community Creator',
            'email' => 'creator@example.com',
            'password' => bcrypt('secret'),
            'role' => 'member',
        ]);

        $member = User::create([
            'nama' => 'Regular Member',
            'email' => 'member@example.com',
            'password' => bcrypt('secret'),
            'role' => 'member',
        ]);

        $community = $this->createCommunity([
            'nama_community' => 'Club to Delete',
            'created_by' => $creator->user_id,
        ]);

        $creatorPlayer = Player::create([
            'user_id' => $creator->user_id,
            'community_id' => $community->community_id,
            'nama' => 'Community Creator',
        ]);

        $memberPlayer = Player::create([
            'user_id' => $member->user_id,
            'community_id' => $community->community_id,
            'nama' => 'Regular Member',
        ]);

        $response = $this->actingAs($creator)->delete('/communities/'.$community->community_id);

        $response->assertRedirect(route('communities.index'));
        $response->assertSessionHas('success');

        // Komunitas sudah terhapus dari DB
        $this->assertDatabaseMissing('tb_community', [
            'community_id' => $community->community_id,
        ]);

        // Anggota komunitas di-set community_id = null (tidak dihapus record pemainnya)
        $creatorPlayer->refresh();
        $memberPlayer->refresh();
        $this->assertNull($creatorPlayer->community_id);
        $this->assertNull($memberPlayer->community_id);
    }

    /**
     * Test 16: User yang bukan pembuat komunitas ditolak saat mencoba menghapus komunitas.
     */
    public function test_non_creator_cannot_delete_community(): void
    {
        $creator = User::create([
            'nama' => 'Real Creator',
            'email' => 'realcreator@example.com',
            'password' => bcrypt('secret'),
            'role' => 'member',
        ]);

        $otherUser = User::create([
            'nama' => 'Other User',
            'email' => 'other@example.com',
            'password' => bcrypt('secret'),
            'role' => 'member',
        ]);

        $community = $this->createCommunity([
            'nama_community' => 'Protected Club',
            'created_by' => $creator->user_id,
        ]);

        $response = $this->actingAs($otherUser)->delete('/communities/'.$community->community_id);

        $response->assertRedirect(route('communities.show', $community->community_id));
        $response->assertSessionHas('error');

        // Komunitas masih tetap ada di DB
        $this->assertDatabaseHas('tb_community', [
            'community_id' => $community->community_id,
        ]);
    }

    /**
     * Test 17: Guest diarahkan ke login saat mencoba menghapus komunitas.
     */
    public function test_guest_cannot_delete_community(): void
    {
        $community = $this->createCommunity(['nama_community' => 'Guest Protected Club']);

        $response = $this->delete('/communities/'.$community->community_id);

        $response->assertRedirect('/login');
        $this->assertDatabaseHas('tb_community', [
            'community_id' => $community->community_id,
        ]);
    }
}
