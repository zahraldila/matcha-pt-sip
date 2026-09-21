<?php

namespace Tests\Feature;

use App\Models\Kudos;
use App\Models\Player;
use App\Models\SessionModel;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class KudosPersistenceTest extends TestCase
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

        if (! Schema::hasTable('tb_session')) {
            Schema::create('tb_session', function ($table) {
                $table->id('session_id');
                $table->unsignedBigInteger('host_user_id')->nullable();
                $table->unsignedBigInteger('sport_id')->nullable();
                $table->unsignedBigInteger('venue_id')->nullable();
                $table->string('nama_session')->default('Session Test');
                $table->string('scoring_system')->default('Total of 3');
                $table->string('status_session')->default('Finished');
                $table->string('jenis_permainan')->default('Double');
                $table->integer('jumlah_pemain')->default(8);
                $table->dateTime('datetime')->nullable();
                $table->string('waktu_session')->nullable();
                $table->timestamps();
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

    public function test_user_can_give_and_toggle_kudos_permanently(): void
    {
        $user = User::create([
            'nama' => 'User Giver',
            'email' => 'giver_'.uniqid().'@matcha.com',
            'role' => 'member',
            'password' => bcrypt('secret'),
        ]);

        $sport = Sport::firstOrCreate(['nama_sport' => 'Padel'], ['status_sport' => 'active']);
        $venue = Venue::create(['nama_venue' => 'Arena Kudos']);

        $session = SessionModel::create([
            'host_user_id' => $user->user_id,
            'sport_id' => $sport->sport_id,
            'venue_id' => $venue->venue_id,
            'nama_session' => 'Mabar Kudos Test',
            'status_session' => 'Finished',
        ]);

        $player = Player::create([
            'user_id' => $user->user_id,
            'nama' => 'lala',
            'level' => 'Beginner',
        ]);

        // 1. Give Kudos '🎾 Super Forehand'
        $res = $this->actingAs($user)->postJson(route('scoring.kudos.toggle'), [
            'session_id' => $session->session_id,
            'player_name' => 'lala',
            'player_id' => $player->player_id,
            'badge' => '🎾 Super Forehand',
        ]);

        $res->assertOk();
        $this->assertTrue($res->json('success'));
        $this->assertTrue($res->json('active'));
        $this->assertEquals(1, $res->json('count'));

        $this->assertDatabaseHas('tb_kudos', [
            'session_id' => $session->session_id,
            'recipient_name' => 'lala',
            'badge' => '🎾 Super Forehand',
        ]);

        // 2. Toggle again (remove kudos)
        $resToggle = $this->actingAs($user)->postJson(route('scoring.kudos.toggle'), [
            'session_id' => $session->session_id,
            'player_name' => 'lala',
            'player_id' => $player->player_id,
            'badge' => '🎾 Super Forehand',
        ]);

        $resToggle->assertOk();
        $this->assertFalse($resToggle->json('active'));
        $this->assertEquals(0, $resToggle->json('count'));

        $this->assertDatabaseMissing('tb_kudos', [
            'session_id' => $session->session_id,
            'recipient_name' => 'lala',
            'badge' => '🎾 Super Forehand',
        ]);
    }

    public function test_guest_cannot_give_kudos(): void
    {
        $user = User::create([
            'nama' => 'Host User',
            'email' => 'host_'.uniqid().'@matcha.com',
            'role' => 'member',
            'password' => bcrypt('secret'),
        ]);

        $sport = Sport::firstOrCreate(['nama_sport' => 'Padel'], ['status_sport' => 'active']);
        $venue = Venue::create(['nama_venue' => 'Arena Kudos Guest']);

        $session = SessionModel::create([
            'host_user_id' => $user->user_id,
            'sport_id' => $sport->sport_id,
            'venue_id' => $venue->venue_id,
            'nama_session' => 'Mabar Kudos Guest Test',
            'status_session' => 'Finished',
        ]);

        $res = $this->postJson(route('scoring.kudos.toggle'), [
            'session_id' => $session->session_id,
            'player_name' => 'lala',
            'badge' => '🎾 Super Forehand',
        ]);

        $res->assertStatus(401);
        $res->assertJson([
            'success' => false,
        ]);

        $this->assertDatabaseMissing('tb_kudos', [
            'session_id' => $session->session_id,
            'recipient_name' => 'lala',
            'badge' => '🎾 Super Forehand',
        ]);
    }
}
