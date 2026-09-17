<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\Drawing;
use App\Models\GameMatch;
use App\Models\Score;
use App\Models\SessionModel;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LiveScoringScoreDowngradeRegressionTest extends TestCase
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
        }
    }

    protected function createCompletedMatchSession()
    {
        $hostUser = User::create([
            'nama' => 'Host Regression',
            'email' => 'host_reg_'.uniqid().'@matcha.com',
            'role' => 'host',
            'password' => bcrypt('secret'),
        ]);

        $sport = Sport::firstOrCreate(['nama_sport' => 'Padel'], ['status_sport' => 'active']);
        $venue = Venue::create(['nama_venue' => 'Arena Regression']);

        $session = SessionModel::create([
            'host_user_id' => $hostUser->user_id,
            'sport_id' => $sport->sport_id,
            'venue_id' => $venue->venue_id,
            'nama_session' => 'Mabar Session Regression',
            'scoring_system' => 'Total of 3',
            'status_session' => 'In Progress',
            'jenis_permainan' => 'Double',
            'jumlah_pemain' => 8,
        ]);

        $court = Court::create(['nama_court' => 'Court 1']);
        DB::table('tb_session_court')->insert([
            'session_id' => $session->session_id,
            'court_id' => $court->court_id,
        ]);

        $drawing = Drawing::create([
            'session_id' => $session->session_id,
            'status_drawing' => 'Locked',
        ]);

        $match = GameMatch::create([
            'drawing_id' => $drawing->drawing_id,
            'court_id' => $court->court_id,
            'nomor_match' => 1,
            'status_match' => 'Completed',
            'hasil_pertandingan' => 'Game Score 6 - 4',
            'winner_team' => 'Team A',
            'version' => 12,
        ]);

        Score::create([
            'match_id' => $match->match_id,
            'set_number' => 1,
            'game_number' => 1,
            'point_score_a' => 'Game',
            'point_score_b' => '0',
            'game_score_a' => 6,
            'game_score_b' => 4,
            'set_score_a' => 1,
            'set_score_b' => 0,
            'score_side_a' => 6,
            'score_side_b' => 4,
            'scoring_system' => 'total_of_sets',
            'status_score' => 'Final',
            'version' => 12,
        ]);

        return [$session, $hostUser, $match];
    }

    /**
     * Test 1: Skor final 6-4 tidak boleh berubah menjadi 5-4 saat stale request datang.
     */
    public function test_stale_completion_request_does_not_downgrade_official_6_4_to_5_4(): void
    {
        [$session, $hostUser, $match] = $this->createCompletedMatchSession();

        // Stale in-flight request dari client yang terlambat mengirimkan skor 5-4
        $res = $this->actingAs($hostUser)->postJson(route('scoring.update-score'), [
            'game_id' => $session->session_id,
            'round' => 'round_1',
            'match_key' => 'round_1',
            'court' => 1,
            'score_a' => 5,
            'score_b' => 4,
            'games_a' => 5,
            'games_b' => 4,
            'point_display_a' => '40',
            'point_display_b' => '30',
            'status' => 'in_progress',
            'client_id' => 'client_stale_1',
            'client_seq' => 2,
            'event_id' => 'evt_stale_5_4',
        ]);

        $res->assertOk();
        // Server harus menolak / mengabaikan downgrade dan mengembalikan skor resmi 6-4
        $this->assertEquals(6, $res->json('score_a'));
        $this->assertEquals(4, $res->json('score_b'));
        $this->assertEquals(6, $res->json('games_a'));
        $this->assertEquals(4, $res->json('games_b'));
    }

    /**
     * Test 2: Saat refresh (getScore atau live view), data skor yang diambil adalah skor resmi 6-4 bukan fallback 5-4 atau 1-0.
     */
    public function test_refresh_reads_authoritative_6_4_score_from_database_even_if_cache_is_empty_or_stale(): void
    {
        [$session, $hostUser, $match] = $this->createCompletedMatchSession();

        // Kosongkan cache untuk menyimulasikan cache miss / restart / refresh
        $cacheKey = "scoring.game_{$session->session_id}";
        Cache::forget($cacheKey);

        // Polling getScore
        $getScoreRes = $this->getJson(url("scoring/get-score/{$session->session_id}/round_1?match_key=round_1"));
        $getScoreRes->assertOk();
        $this->assertEquals(6, $getScoreRes->json('score_a'));
        $this->assertEquals(4, $getScoreRes->json('score_b'));
        $this->assertEquals(6, $getScoreRes->json('games_a'));
        $this->assertEquals(4, $getScoreRes->json('games_b'));
        $this->assertEquals('completed', $getScoreRes->json('status'));

        // Simulasikan stale cache 5-4 dengan version lebih tinggi
        Cache::put($cacheKey, [
            'round_1' => [
                'version' => 99,
                'status' => 'in_progress',
                'score_a' => 5,
                'score_b' => 4,
                'games_a' => 5,
                'games_b' => 4,
            ],
        ], now()->addHours(1));

        // Polling ulang: DB yang completed 6-4 harus meng-override stale cache 5-4
        $getScoreRes2 = $this->getJson(url("scoring/get-score/{$session->session_id}/round_1?match_key=round_1"));
        $getScoreRes2->assertOk();
        $this->assertEquals(6, $getScoreRes2->json('score_a'));
        $this->assertEquals(4, $getScoreRes2->json('score_b'));
        $this->assertEquals(6, $getScoreRes2->json('games_a'));
        $this->assertEquals(4, $getScoreRes2->json('games_b'));
    }
}
