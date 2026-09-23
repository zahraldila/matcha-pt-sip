<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicVenueTest extends TestCase
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
                $table->text('foto')->nullable();
                $table->string('fasilitas')->nullable();
                $table->text('catatan')->nullable();
                $table->string('kota')->nullable();
                $table->string('jam_operasional')->nullable();
                $table->string('hari_buka')->nullable();
                $table->string('no_whatsapp')->nullable();
                $table->string('nama_pic')->nullable();
                $table->string('google_maps_url')->nullable();
                $table->string('sport_type')->nullable();
                $table->string('tipe_arena')->nullable();
                $table->string('jenis_permukaan')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tb_court')) {
            Schema::create('tb_court', function ($table) {
                $table->id('court_id');
                $table->unsignedBigInteger('venue_id');
                $table->unsignedBigInteger('sport_id')->nullable();
                $table->string('nama_court');
                $table->string('status_ketersediaan')->default('Available');
                $table->text('image_url')->nullable();
                $table->text('deskripsi')->nullable();
                $table->string('tipe_court')->nullable();
                $table->decimal('harga_per_jam', 12, 2)->default(0)->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Helper untuk membuat venue valid dengan owner.
     */
    protected function createValidVenue(string $name = 'Gelora Sports Center'): Venue
    {
        $owner = User::create([
            'nama' => 'Owner Pak Bambang',
            'email' => 'owner_'.uniqid().'@matcha.com',
            'role' => 'venue_owner',
            'password' => bcrypt('secret'),
        ]);

        return Venue::create([
            'owner_user_id' => $owner->user_id,
            'nama_venue' => $name,
            'alamat' => 'Jl. Sudirman No. 45, Jakarta Pusat',
            'kota' => 'Jakarta',
            'jam_operasional' => '06:00 - 23:00',
            'hari_buka' => 'Setiap Hari (Senin - Minggu)',
            'nama_pic' => 'Pak Bambang',
            'no_whatsapp' => '081234567890',
        ]);
    }

    /**
     * Test a: Guest membuka venue valid -> HTTP 200 + nama venue benar.
     */
    public function test_guest_can_access_valid_venue_and_sees_correct_name(): void
    {
        $this->assertGuest();

        $venue = $this->createValidVenue('Matcha Grand Arena');

        $response = $this->get('/venues/'.$venue->venue_id);

        $response->assertStatus(200);
        $response->assertSee('Matcha Grand Arena');
    }

    /**
     * Test b: Guest membuka venue ID 999999 -> HTTP 404, bukan 500.
     */
    public function test_guest_accessing_nonexistent_venue_id_999999_returns_404(): void
    {
        $this->assertGuest();

        $response = $this->get('/venues/999999');

        $response->assertStatus(404);
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    /**
     * Test c: Guest membuka venue ID 0 -> HTTP 404, bukan 500.
     */
    public function test_guest_accessing_invalid_venue_id_0_returns_404(): void
    {
        $this->assertGuest();

        $response = $this->get('/venues/0');

        $response->assertStatus(404);
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    /**
     * Test d: Venue valid tanpa court -> HTTP 200 dan menampilkan empty state yang sesuai.
     */
    public function test_guest_can_access_valid_venue_without_courts_and_sees_empty_state(): void
    {
        $this->assertGuest();

        $venue = $this->createValidVenue('Venue Sunyi');

        $this->assertEquals(0, $venue->courts()->count());

        $response = $this->get('/venues/'.$venue->venue_id);

        $response->assertStatus(200);
        $response->assertSee('Venue Sunyi');
        $response->assertSee('Belum ada court yang terdaftar untuk venue ini.');
    }

    /**
     * Test e: Venue valid dengan court -> informasi sport/court dapat dirender tanpa exception.
     */
    public function test_guest_can_access_valid_venue_with_courts_and_renders_sport_and_court_info(): void
    {
        $this->assertGuest();

        $venue = $this->createValidVenue('Cilandak Padel Club');

        $sportTennis = Sport::create([
            'nama_sport' => 'Tennis',
            'status_sport' => 'active',
        ]);

        $sportPadel = Sport::create([
            'nama_sport' => 'Padel',
            'status_sport' => 'active',
        ]);

        $court1 = Court::create([
            'venue_id' => $venue->venue_id,
            'sport_id' => $sportTennis->sport_id,
            'nama_court' => 'Center Court Hard',
            'status_ketersediaan' => 'Available',
            'tipe_court' => 'Outdoor Hard Court',
        ]);

        $court2 = Court::create([
            'venue_id' => $venue->venue_id,
            'sport_id' => $sportPadel->sport_id,
            'nama_court' => 'Panoramic Glass 1',
            'status_ketersediaan' => 'Available',
            'tipe_court' => 'Indoor Panoramic',
        ]);

        $response = $this->get('/venues/'.$venue->venue_id);

        $response->assertStatus(200);
        $response->assertSee('Center Court Hard');
        $response->assertSee('Tennis');
        $response->assertSee('Panoramic Glass 1');
        $response->assertSee('Padel');
        $response->assertSee('Padel &amp; Tennis', false);
    }

    /**
     * Test f: Venue valid dengan court yang memiliki sport null dirender tanpa exception.
     */
    public function test_guest_can_access_venue_with_court_having_null_sport_without_exception(): void
    {
        $this->assertGuest();

        $venue = $this->createValidVenue('Minimalist Venue');

        $court = Court::create([
            'venue_id' => $venue->venue_id,
            'sport_id' => null,
            'nama_court' => 'Mystery Court',
            'status_ketersediaan' => 'Available',
            'tipe_court' => 'Standard',
        ]);

        $response = $this->get('/venues/'.$venue->venue_id);

        $response->assertStatus(200);
        $response->assertSee('Mystery Court');
        $this->assertNotEquals(500, $response->getStatusCode());
    }

    /**
     * Test g (BUG-PUB-003): Guest membuka venue nonexistent tidak menampilkan data venue lain (Gelora Sports Center).
     */
    public function test_guest_accessing_nonexistent_venue_does_not_display_other_venue_data(): void
    {
        $this->assertGuest();

        // Pastikan venue 'Gelora Sports Center' ada di DB
        $this->createValidVenue('Gelora Sports Center');

        $response = $this->get('/venues/999999');

        $response->assertStatus(404);
        $response->assertDontSee('Gelora Sports Center');
    }

    /**
     * Test h (BUG-PUB-004): Venue dengan URL foto valid merender src dengan benar.
     */
    public function test_venue_with_valid_photo_url_renders_correct_src(): void
    {
        $this->assertGuest();

        $venue = $this->createValidVenue('Venue Foto Valid');
        $venue->update([
            'foto' => 'https://xkyneehswdqkdgzodwdc.supabase.co/storage/v1/object/public/venues/sample1.png',
        ]);

        $response = $this->get('/venues/'.$venue->venue_id);

        $response->assertStatus(200);
        $response->assertSee('https://xkyneehswdqkdgzodwdc.supabase.co/storage/v1/object/public/venues/sample1.png', false);
    }

    /**
     * Test i (BUG-PUB-004): Venue dengan path lokal tidak ada + URL Supabase valid -> Supabase dipilih sebagai main photo.
     */
    public function test_venue_with_missing_local_path_and_valid_supabase_url_picks_supabase_as_main_photo(): void
    {
        $this->assertGuest();

        $supabaseUrl = 'https://xkyneehswdqkdgzodwdc.supabase.co/storage/v1/object/public/venues/sample_valid.png';
        $venue = $this->createValidVenue('Venue Campuran Foto');
        $venue->update([
            'foto' => 'uploads/venues/nonexistent_file_9999.png, '.$supabaseUrl,
        ]);

        $response = $this->get('/venues/'.$venue->venue_id);

        $response->assertStatus(200);
        $response->assertSee($supabaseUrl, false);
        $response->assertDontSee('uploads/venues/nonexistent_file_9999.png');
    }

    /**
     * Test j (BUG-PUB-004): Venue hanya memiliki path lokal yang tidak ada -> main photo null dan placeholder tampil.
     */
    public function test_venue_with_only_missing_local_paths_shows_placeholder_and_no_broken_image(): void
    {
        $this->assertGuest();

        $venue = $this->createValidVenue('Venue Foto Hilang');
        $venue->update([
            'foto' => 'uploads/venues/missing_1.png, uploads/venues/missing_2.png',
        ]);

        $response = $this->get('/venues/'.$venue->venue_id);

        $response->assertStatus(200);
        $response->assertSee('Belum ada foto venue yang diunggah');
        $response->assertDontSee('uploads/venues/missing_1.png');
        $response->assertDontSee('uploads/venues/missing_2.png');
    }

    /**
     * Test k (BUG-PUB-004): Venue tanpa foto -> tidak menggunakan Unsplash dummy dan menampilkan placeholder.
     */
    public function test_venue_without_photos_does_not_use_unsplash_dummy_and_shows_placeholder(): void
    {
        $this->assertGuest();

        $venue = $this->createValidVenue('Venue Polos');
        $venue->update([
            'foto' => null,
        ]);

        $response = $this->get('/venues/'.$venue->venue_id);

        $response->assertStatus(200);
        $response->assertSee('Belum ada foto venue yang diunggah');
        $response->assertDontSee('images.unsplash.com');
    }

    /**
     * Test l (BUG-PUB-004): Fasilitas string kosong -> tidak menghasilkan item kosong dan menampilkan empty state.
     */
    public function test_venue_with_empty_facilities_string_does_not_produce_empty_item_and_shows_empty_state(): void
    {
        $this->assertGuest();

        $venue = $this->createValidVenue('Venue Fasilitas Kosong');
        $venue->update([
            'fasilitas' => '',
        ]);

        $response = $this->get('/venues/'.$venue->venue_id);

        $response->assertStatus(200);
        $response->assertSee('Belum ada informasi fasilitas untuk venue ini.');
        // Memastikan tidak ada <span></span> kosong di dalam fasilitas
        $response->assertDontSee('<span></span>', false);
    }

    /**
     * Test m (BUG-PUB-004): Fasilitas dengan spasi -> whitespace sudah di-trim dengan bersih.
     */
    public function test_venue_with_facilities_trims_whitespace_properly(): void
    {
        $this->assertGuest();

        $venue = $this->createValidVenue('Venue Fasilitas Spasi');
        $venue->update([
            'fasilitas' => 'WC ,  Kantin Sehat , Ruang Ganti ',
        ]);

        $response = $this->get('/venues/'.$venue->venue_id);

        $response->assertStatus(200);
        $response->assertSee('<span>WC</span>', false);
        $response->assertSee('<span>Kantin Sehat</span>', false);
        $response->assertSee('<span>Ruang Ganti</span>', false);
    }

    /**
     * Test BUG-VEN-001, BUG-VEN-002, BUG-VEN-003: Menambahkan court tipe Indoor, Outdoor, & Semi-Indoor tersimpan & tampil dengan benar.
     */
    public function test_court_registration_saves_and_displays_court_types_correctly(): void
    {
        $venue = $this->createValidVenue('Venue Lapangan Lengkap');
        $owner = User::find($venue->owner_user_id);

        $this->actingAs($owner);

        // Batch registration of 3 courts: Indoor, Outdoor, Semi-Indoor
        $response = $this->post("/venues/{$venue->venue_id}/courts", [
            'courts' => [
                [
                    'nama_court' => 'Court 1 - Indoor',
                    'tipe_court' => 'Indoor',
                    'harga_per_jam' => 150000,
                ],
                [
                    'nama_court' => 'Court 2 - Outdoor',
                    'tipe_court' => 'Outdoor',
                    'harga_per_jam' => 120000,
                ],
                [
                    'nama_court' => 'Court 3 - Semi',
                    'tipe_court' => 'Semi-Indoor',
                    'harga_per_jam' => 140000,
                ],
            ],
        ]);

        $response->assertRedirect("/venues/{$venue->venue_id}");

        // Assert database values
        $this->assertDatabaseHas('tb_court', [
            'venue_id' => $venue->venue_id,
            'nama_court' => 'Court 1 - Indoor',
            'tipe_court' => 'Indoor',
        ]);
        $this->assertDatabaseHas('tb_court', [
            'venue_id' => $venue->venue_id,
            'nama_court' => 'Court 2 - Outdoor',
            'tipe_court' => 'Outdoor',
        ]);
        $this->assertDatabaseHas('tb_court', [
            'venue_id' => $venue->venue_id,
            'nama_court' => 'Court 3 - Semi',
            'tipe_court' => 'Semi-Indoor',
        ]);

        // Assert show page renders court types correctly
        $showResponse = $this->get("/venues/{$venue->venue_id}");
        $showResponse->assertStatus(200);
        $showResponse->assertSee('Tipe: <strong class="text-slate-700">Indoor</strong>', false);
        $showResponse->assertSee('Tipe: <strong class="text-slate-700">Outdoor</strong>', false);
        $showResponse->assertSee('Tipe: <strong class="text-slate-700">Semi-Indoor</strong>', false);
        $showResponse->assertDontSee('Tidak ditentukan');
    }

    /**
     * Test BUG-VEN-004: Mengirim tipe court yang tidak valid ditolak oleh sistem dengan error validasi.
     */
    public function test_court_registration_rejects_invalid_court_type(): void
    {
        $venue = $this->createValidVenue('Venue Test Invalid Type');
        $owner = User::find($venue->owner_user_id);
        $this->actingAs($owner);

        $response = $this->post("/venues/{$venue->venue_id}/courts", [
            'courts' => [
                [
                    'nama_court' => 'Court A',
                    'tipe_court' => 'SuperIndoor', // Invalid type
                    'harga_per_jam' => 100000,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['courts.0.tipe_court']);
    }

    /**
     * Test BUG-VEN-005: Harga per jam tersimpan & tampil di kartu court detail venue.
     */
    public function test_court_registration_displays_hourly_price_on_court_card(): void
    {
        $venue = $this->createValidVenue('Venue Price Card');
        $owner = User::find($venue->owner_user_id);
        $this->actingAs($owner);

        $this->post("/venues/{$venue->venue_id}/courts", [
            'courts' => [
                [
                    'nama_court' => 'Court Utama',
                    'tipe_court' => 'Indoor',
                    'harga_per_jam' => 175000,
                ],
            ],
        ]);

        $response = $this->get("/venues/{$venue->venue_id}");
        $response->assertStatus(200);
        $response->assertSee('175,000');
        $response->assertSee('/jam');
    }

    /**
     * Test BUG-VEN-006: Memastikan pesan sukses dikirim ke session setelah penambahan court.
     */
    public function test_court_registration_shows_success_flash_message(): void
    {
        $venue = $this->createValidVenue('Venue Flash Feedback');
        $owner = User::find($venue->owner_user_id);
        $this->actingAs($owner);

        $response = $this->post("/venues/{$venue->venue_id}/courts", [
            'courts' => [
                [
                    'nama_court' => 'Court 1',
                    'tipe_court' => 'Indoor',
                    'harga_per_jam' => 100000,
                ],
            ],
        ]);

        $response->assertRedirect("/venues/{$venue->venue_id}");
        $response->assertSessionHas('success');
    }

    /**
     * Test BUG-VEN-007: Nama venue > 100 karakter ditolak oleh sistem validasi.
     */
    public function test_venue_registration_validates_max_venue_name_length(): void
    {
        $owner = User::create([
            'nama' => 'Owner Max Length',
            'email' => 'owner_maxlen_'.uniqid().'@matcha.com',
            'role' => 'venue_owner',
            'password' => bcrypt('secret'),
        ]);
        $this->actingAs($owner);

        $longName = str_repeat('A', 101);

        $response = $this->post('/venues', [
            'nama_venue' => $longName,
            'alamat' => 'Jl. Panjang No. 1',
        ]);

        $response->assertSessionHasErrors(['nama_venue']);
    }

    /**
     * Test BUG-VEN-008: Nama court > 100 karakter ditolak oleh sistem validasi.
     */
    public function test_court_registration_validates_max_court_name_length(): void
    {
        $venue = $this->createValidVenue('Venue Max Court Name');
        $owner = User::find($venue->owner_user_id);
        $this->actingAs($owner);

        $longCourtName = str_repeat('C', 101);

        $response = $this->post("/venues/{$venue->venue_id}/courts", [
            'courts' => [
                [
                    'nama_court' => $longCourtName,
                    'tipe_court' => 'Indoor',
                    'harga_per_jam' => 100000,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['courts.0.nama_court']);
    }

    /**
     * Test Duplicate Venue Name: Mendaftarkan nama venue yang sudah ada ditolak oleh validasi unik.
     */
    public function test_venue_registration_rejects_duplicate_venue_name(): void
    {
        $existingVenue = $this->createValidVenue('Gelora Padel Indonesia');

        $owner = User::create([
            'nama' => 'Owner Lain',
            'email' => 'owner_lain_'.uniqid().'@matcha.com',
            'role' => 'venue_owner',
            'password' => bcrypt('secret'),
        ]);
        $this->actingAs($owner);

        $response = $this->post('/venues', [
            'nama_venue' => 'Gelora Padel Indonesia',
            'alamat' => 'Jl. Kebon Jeruk No. 2',
        ]);

        $response->assertSessionHasErrors(['nama_venue']);
    }

    /**
     * Test Edit Venue: Owner dapat mengakses form edit venue.
     */
    public function test_owner_can_access_edit_venue_page(): void
    {
        $venue = $this->createValidVenue('Venue Edit Test');
        $owner = User::find($venue->owner_user_id);
        $this->actingAs($owner);

        $response = $this->get("/venues/{$venue->venue_id}/edit");
        $response->assertStatus(200);
        $response->assertSee('Edit Informasi Venue');
        $response->assertSee('Venue Edit Test');
    }

    /**
     * Test Update Venue: Owner berhasil memperbarui data venue di database.
     */
    public function test_owner_can_update_venue_in_database(): void
    {
        $venue = $this->createValidVenue('Venue Original');
        $owner = User::find($venue->owner_user_id);
        $this->actingAs($owner);

        $response = $this->put("/venues/{$venue->venue_id}", [
            'nama_venue' => 'Venue Updated Name',
            'alamat' => 'Jl. Baru No. 99',
            'kota' => 'Bandung',
            'sport_type' => 'Padel',
            'tipe_arena' => 'Indoor',
            'jenis_permukaan' => 'Karpet Interlock',
            'jam_operasional' => '08:00 - 22:00 WIB',
            'hari_buka' => 'Setiap Hari (Senin - Minggu)',
            'nama_pic' => 'PIC Baru',
            'no_whatsapp' => '0899999999',
            'catatan' => 'Catatan diperbarui',
            'facilities' => ['Parkir Luas', 'Wi-Fi Gratis'],
        ]);

        $response->assertRedirect(route('venues.show', $venue->venue_id));
        $this->assertDatabaseHas('tb_venue', [
            'venue_id' => $venue->venue_id,
            'nama_venue' => 'Venue Updated Name',
            'alamat' => 'Jl. Baru No. 99',
            'kota' => 'Bandung',
            'nama_pic' => 'PIC Baru',
        ]);
    }

    /**
     * Test Update Court: Owner berhasil memperbarui data court di database.
     */
    public function test_owner_can_update_court_in_database(): void
    {
        $venue = $this->createValidVenue('Venue Court Edit Test');
        $owner = User::find($venue->owner_user_id);
        $this->actingAs($owner);

        $sport = Sport::create(['nama_sport' => 'Padel', 'status_sport' => 'Active']);
        $court = Court::create([
            'venue_id' => $venue->venue_id,
            'sport_id' => $sport->sport_id,
            'nama_court' => 'Court A Lama',
            'status_ketersediaan' => 'Available',
            'tipe_court' => 'Indoor',
            'harga_per_jam' => 100000,
        ]);

        $response = $this->put("/venues/{$venue->venue_id}/courts/{$court->court_id}", [
            'nama_court' => 'Court A Premium',
            'sport_name' => 'Padel',
            'tipe_court' => 'Outdoor',
            'harga_per_jam' => 200000,
            'status_ketersediaan' => 'Maintenance',
        ]);

        $response->assertRedirect(route('venues.show', $venue->venue_id));
        $this->assertDatabaseHas('tb_court', [
            'court_id' => $court->court_id,
            'nama_court' => 'Court A Premium',
            'tipe_court' => 'Outdoor',
            'harga_per_jam' => 200000,
            'status_ketersediaan' => 'Maintenance',
        ]);
    }
}
