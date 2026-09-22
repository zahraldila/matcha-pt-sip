<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class QuickAddVenueTest extends TestCase
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
                $table->boolean('is_host')->default(true);
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
                $table->string('kota')->nullable();
                $table->string('foto')->nullable();
                $table->string('fasilitas')->nullable();
                $table->text('catatan')->nullable();
                $table->string('jam_operasional')->nullable();
                $table->string('hari_buka')->nullable();
                $table->string('no_whatsapp')->nullable();
                $table->string('nama_pic')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tb_court')) {
            Schema::create('tb_court', function ($table) {
                $table->id('court_id');
                $table->unsignedBigInteger('venue_id');
                $table->unsignedBigInteger('sport_id');
                $table->string('nama_court');
                $table->string('status_ketersediaan')->default('Available');
                $table->string('image_url')->nullable();
                $table->text('deskripsi')->nullable();
                $table->string('tipe_court')->nullable();
                $table->decimal('harga_per_jam', 12, 2)->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_authenticated_user_can_quick_store_new_venue_with_courts(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'host_quick@example.com'],
            ['nama' => 'Host Quick Add', 'is_host' => true]
        );

        $sport = Sport::firstOrCreate(
            ['nama_sport' => 'Padel'],
            ['status_sport' => 'Active']
        );

        $uniqueName = 'Quick Arena '.uniqid();

        $response = $this->actingAs($user)->postJson(route('venues.quickStore'), [
            'nama_venue' => $uniqueName,
            'sport' => 'Padel',
            'jumlah_court' => 3,
            'kota' => 'Bandung',
            'alamat' => 'Jl. Riau No. 88',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('tb_venue', [
            'nama_venue' => $uniqueName,
            'kota' => 'Bandung',
        ]);

        $venue = Venue::where('nama_venue', $uniqueName)->first();
        $this->assertNotNull($venue);

        $courts = Court::where('venue_id', $venue->venue_id)->get();
        $this->assertCount(3, $courts);
        $this->assertEquals('Court 1', $courts[0]->nama_court);
        $this->assertEquals('Available', $courts[0]->status_ketersediaan);
    }

    public function test_quick_store_venue_validation_duplicate_name(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'host_dup@example.com'],
            ['nama' => 'Host Duplicate Check', 'is_host' => true]
        );

        $sport = Sport::firstOrCreate(
            ['nama_sport' => 'Tennis'],
            ['status_sport' => 'Active']
        );

        $venueName = 'Existing Club '.uniqid();
        Venue::create([
            'nama_venue' => $venueName,
            'owner_user_id' => $user->user_id,
        ]);

        $response = $this->actingAs($user)->postJson(route('venues.quickStore'), [
            'nama_venue' => $venueName,
            'sport' => 'Tennis',
            'jumlah_court' => 2,
        ]);

        $response->assertStatus(422);
    }

    public function test_quick_store_venue_rejects_script_payload(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'host_xss@example.com'],
            ['nama' => 'Host XSS Check', 'is_host' => true]
        );

        $response = $this->actingAs($user)->postJson(route('venues.quickStore'), [
            'nama_venue' => '<script>alert(1)</script>',
            'sport' => 'Padel',
            'jumlah_court' => 1,
            'kota' => 'Jakarta',
            'alamat' => 'Jl. Test',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['nama_venue']);
    }
}
