<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        if (! Schema::hasTable('tb_sport')) {
            Schema::create('tb_sport', function ($table) {
                $table->id('sport_id');
                $table->string('nama_sport')->default('Padel');
                $table->string('status_sport')->default('active');
            });
        }
        if (! Schema::hasTable('tb_user')) {
            Schema::create('tb_user', function ($table) {
                $table->id('user_id');
                $table->string('nama')->default('User Test');
                $table->string('email')->unique();
                $table->string('password')->default('password');
                $table->string('role')->default('member');
                $table->boolean('is_host')->default(false);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('tb_venue')) {
            Schema::create('tb_venue', function ($table) {
                $table->id('venue_id');
                $table->unsignedBigInteger('owner_user_id')->nullable();
                $table->string('nama_venue')->default('Venue Test');
                $table->string('alamat')->nullable();
                $table->string('foto')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('tb_court')) {
            Schema::create('tb_court', function ($table) {
                $table->id('court_id');
                $table->unsignedBigInteger('venue_id')->nullable();
                $table->unsignedBigInteger('sport_id')->nullable();
                $table->string('nama_court')->default('Court 1');
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('tb_community')) {
            Schema::create('tb_community', function ($table) {
                $table->id('community_id');
                $table->string('nama_komunitas')->default('Komunitas Test');
                $table->string('kota')->nullable();
                $table->string('logo')->nullable();
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

        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
