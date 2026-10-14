<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Models\Drawing;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\Score;
use App\Models\SessionModel;
use App\Models\Sport;
use App\Models\User;
use App\Models\Venue;
use App\Services\Scoring\ScoringService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EndMatchNextRoundRegressionTest extends TestCase
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
                $table->string('share_token')->nullable();
                $table->dateTime('datetime')->nullable();
                $table->string('waktu_session')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('tb_session') && ! Schema::hasColumn('tb_session', 'active_round')) {
            Schema::table('tb_session', function ($table) {
                $table->string('active_round', 32)->nullable();
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
                $table->string('status')->default('active');
                $table->timestamp('withdrawn_at')->nullable();
                $table->string('withdrawn_reason')->nullable();
                $table->unsignedBigInteger('withdrawn_match_id')->nullable();
            });
        }

        if (! Schema::hasTable('tb_match_format')) {
            Schema::create('tb_match_format', function ($table) {
                $table->id('match_format_id');
                $table->string('nama_format');
            });
            DB::table('tb_match_format')->insertOrIgnore([
                ['match_format_id' => 1, 'nama_format' => 'Americano'],
                ['match_format_id' => 4, 'nama_format' => 'Team Americano'],
            ]);
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
                $table->string('stop_type')->nullable();
                $table->string('stop_reason')->nullable();
                $table->unsignedBigInteger('stopped_by_user_id')->nullable();
                $table->dateTime('stopped_at')->nullable();
                $table->text('withdrawn_player_ids')->nullable();
                $table->text('player_notes')->nullable();
                $table->integer('version')->default(1);
                $table->string('last_event_id')->nullable();
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
                $table->integer('version')->default(1);
                $table->string('last_event_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tb_match_participant')) {
            Schema::create('tb_match_participant', function ($table) {
                $table->unsignedBigInteger('match_id');
                $table->unsignedBigInteger('player_id');
                $table->string('side', 10)->default('A');
            });
        }
    }

    protected function createIsolatedSession(int $courtCount = 1, int $playerCount = 8)
    {
        $unique = uniqid('rgr_', true);
        $host = User::create([
            'nama' => 'Host '.$unique,
            'email' => 'host_'.$unique.'@test.com',
            'role' => 'host',
        ]);

        $sport = Sport::create(['nama_sport' => 'Tennis']);
        $venue = Venue::create(['nama_venue' => 'Matcha Arena '.$unique, 'owner_user_id' => $host->user_id]);

        $session = SessionModel::create([
            'host_user_id' => $host->user_id,
            'sport_id' => $sport->sport_id,
            'venue_id' => $venue->venue_id,
            'nama_session' => 'Session '.$unique,
            'scoring_system' => 'Total of 3',
            'status_session' => 'In Progress',
            'jenis_permainan' => 'Double',
            'jumlah_pemain' => $playerCount,
        ]);

        $courts = [];
        for ($i = 1; $i <= $courtCount; $i++) {
            $court = Court::create(['nama_court' => "Court {$i}"]);
            DB::table('tb_session_court')->insert([
                'session_id' => $session->session_id,
                'court_id' => $court->court_id,
            ]);
            $courts[] = $court;
        }

        $names = ['Alice', 'Bob', 'Charlie', 'David', 'Eva', 'Frank', 'Grace', 'Henry', 'Ivy', 'Jack'];
        $players = [];
        for ($i = 0; $i < $playerCount; $i++) {
            $pName = $names[$i] ?? ('Player '.$i);
            $user = User::create([
                'nama' => $pName,
                'email' => strtolower($pName).'_'.$unique.'@test.com',
                'role' => 'member',
            ]);
            $pl = Player::create([
                'user_id' => $user->user_id,
                'nama' => $pName,
            ]);
            DB::table('tb_session_player')->insert([
                'session_id' => $session->session_id,
                'player_id' => $pl->player_id,
                'status' => 'active',
            ]);
            $players[] = $pl;
        }

        $drawing = Drawing::create([
            'session_id' => $session->session_id,
            'match_format_id' => 1,
            'tanggal_drawing' => now()->toDateString(),
            'jam_drawing' => now()->toTimeString(),
        ]);

        // Siapkan jadwal 3 ronde
        $rounds = [
            1 => [
                'round' => 1,
                'matches' => [],
            ],
            2 => [
                'round' => 2,
                'matches' => [],
            ],
            3 => [
                'round' => 3,
                'matches' => [],
            ],
        ];

        for ($r = 1; $r <= 3; $r++) {
            for ($c = 1; $c <= $courtCount; $c++) {
                $rounds[$r]['matches'][] = [
                    'court' => $c,
                    'court_name' => "Court {$c}",
                    'team_a' => [$players[0]->player_id, $players[1]->player_id],
                    'team_b' => [$players[2]->player_id, $players[3]->player_id],
                    'team_a_names' => [$players[0]->nama, $players[1]->nama],
                    'team_b_names' => [$players[2]->nama, $players[3]->nama],
                ];
            }
        }

        Cache::put("drawing.schedule_{$session->session_id}", [
            'rounds' => $rounds,
            'court_count' => $courtCount,
        ], now()->addHours(4));
        Cache::put("drawing.locked_{$session->session_id}", true, now()->addHours(4));

        return [$session, $host, $players, $drawing, $courts, $rounds];
    }

    /**
     * Skenario A & B (Mode A): Ronde 1 selesai normal -> Ronde 2 dihentikan (Mode A) -> POST nextRound -> Drawing Ronde 3 tampil & accessible.
     */
    public function test_scenario_a_and_b_mode_a_walkover_advances_to_round_3_drawing(): void
    {
        [$session, $host, $players, $drawing, $courts, $rounds] = $this->createIsolatedSession(1, 8);
        $this->actingAs($host);

        $gameId = $session->session_id;

        // 1. Ronde 1 selesai normal (completed)
        $match1 = GameMatch::create([
            'drawing_id' => $drawing->drawing_id,
            'court_id' => $courts[0]->court_id,
            'nomor_match' => 1,
            'status_match' => 'Completed',
            'waktu_mulai' => now()->subMinutes(30),
            'waktu_selesai' => now()->subMinutes(10),
            'hasil_pertandingan' => '6-2',
            'winner_team' => 'team_a',
        ]);
        Score::create([
            'match_id' => $match1->match_id,
            'set_number' => 1,
            'game_score_a' => 6,
            'game_score_b' => 2,
            'status_score' => 'Final',
        ]);
        Cache::put("scoring.game_{$gameId}", [
            '_meta' => ['active_round' => 'round_1'],
            'round_1' => [
                'status' => 'completed',
                'games_a' => 6,
                'games_b' => 2,
            ],
        ], now()->addHours(4));

        // Transisi ke ronde 2
        $respNext1 = $this->post(route('scoring.next-round'), [
            'game_id' => $gameId,
            'current_round' => 'round_1',
            'next_round' => 'round_2',
        ]);
        $respNext1->assertRedirect(route('games.drawing', ['id' => $gameId, 'round' => 2]));

        // 2. Ronde 2 dihentikan via End Match Mode A (keep_in_session)
        $match2 = GameMatch::create([
            'drawing_id' => $drawing->drawing_id,
            'court_id' => $courts[0]->court_id,
            'nomor_match' => 2,
            'status_match' => 'In Progress',
            'waktu_mulai' => now()->subMinutes(5),
        ]);

        $stopResp = $this->postJson(route('scoring.stop-match'), [
            'game_id' => $gameId,
            'match_id' => $match2->match_id,
            'match_key' => 'round_2',
            'round' => 'round_2',
            'stop_type' => 'keep_in_session',
            'reason' => 'Cedera ringan - tetap di sesi',
            'related_player_ids' => [$players[0]->player_id],
            'current_games_a' => 2,
            'current_games_b' => 1,
        ]);
        $stopResp->assertOk();
        $stopResp->assertJson(['success' => true]);

        // Verifikasi status match 2 adalah Stopped
        $this->assertEquals('Stopped', $match2->fresh()->status_match);

        // 3. Host memilih lanjut ke ronde 3
        $respNext2 = $this->post(route('scoring.next-round'), [
            'game_id' => $gameId,
            'current_round' => 'round_2',
            'next_round' => 'round_3',
        ]);
        $respNext2->assertRedirect(route('games.drawing', ['id' => $gameId, 'round' => 3]));

        // 4. Buka halaman drawing ronde 3
        $drawingResp = $this->get(route('games.drawing', ['id' => $gameId, 'round' => 3]));
        $drawingResp->assertOk();
        $drawingResp->assertViewHas('activeRoundNum', 3);
        $roundAccess = $drawingResp->viewData('roundAccess');
        $roundStatus = $drawingResp->viewData('roundStatus');

        $this->assertTrue($roundAccess[1]);
        $this->assertTrue($roundAccess[2]);
        $this->assertTrue($roundAccess[3]);
        $this->assertEquals('completed', $roundStatus[1]);
        $this->assertEquals('completed', $roundStatus[2]); // Ronde 2 terminal karena stopped
    }

    /**
     * Skenario B (Mode B): Ronde 2 dihentikan dengan Mode B (withdraw_players) -> Next Round -> Drawing Ronde 3 accessible.
     */
    public function test_scenario_b_mode_b_withdrawal_advances_to_round_3(): void
    {
        [$session, $host, $players, $drawing, $courts, $rounds] = $this->createIsolatedSession(1, 8);
        $this->actingAs($host);
        $gameId = $session->session_id;

        // Ronde 1 completed
        $match1 = GameMatch::create([
            'drawing_id' => $drawing->drawing_id,
            'court_id' => $courts[0]->court_id,
            'nomor_match' => 1,
            'status_match' => 'Completed',
        ]);
        Cache::put("scoring.game_{$gameId}", [
            '_meta' => ['active_round' => 'round_2'],
            'round_1' => ['status' => 'completed', 'games_a' => 6, 'games_b' => 1],
        ], now()->addHours(4));

        $match2 = GameMatch::create([
            'drawing_id' => $drawing->drawing_id,
            'court_id' => $courts[0]->court_id,
            'nomor_match' => 2,
            'status_match' => 'In Progress',
        ]);

        // End Match Mode B: withdraw player 0
        $stopResp = $this->postJson(route('scoring.stop-match'), [
            'game_id' => $gameId,
            'match_id' => $match2->match_id,
            'match_key' => 'round_2',
            'round' => 'round_2',
            'stop_type' => 'withdraw_players',
            'reason' => 'Cedera berat keluar sesi',
            'withdrawn_player_ids' => [$players[0]->player_id],
            'current_games_a' => 1,
            'current_games_b' => 2,
        ]);
        $stopResp->assertOk();

        // Verifikasi player 0 withdrawn
        $withdrawnRow = DB::table('tb_session_player')
            ->where('session_id', $gameId)
            ->where('player_id', $players[0]->player_id)
            ->first();
        $this->assertEquals('withdrawn', $withdrawnRow->status);

        // Lanjut ke ronde 3
        $respNext = $this->post(route('scoring.next-round'), [
            'game_id' => $gameId,
            'current_round' => 'round_2',
            'next_round' => 'round_3',
        ]);
        $respNext->assertRedirect(route('games.drawing', ['id' => $gameId, 'round' => 3]));

        // Akses drawing ronde 3
        $drawingResp = $this->get(route('games.drawing', ['id' => $gameId, 'round' => 3]));
        $drawingResp->assertOk();
        $this->assertEquals(3, $drawingResp->viewData('activeRoundNum'));
        $this->assertTrue($drawingResp->viewData('roundAccess')[3]);
    }

    /**
     * Skenario C: Multi-court (2 courts):
     * Court 1 stopped, Court 2 masih in_progress -> nextRound DITOLAK.
     * Court 2 selesai -> nextRound BERHASIL.
     */
    public function test_scenario_c_multi_court_requires_all_courts_terminal(): void
    {
        [$session, $host, $players, $drawing, $courts, $rounds] = $this->createIsolatedSession(2, 8);
        $this->actingAs($host);
        $gameId = $session->session_id;

        // Ronde 1 memiliki 2 court: nomor_match 1 (Court 1) dan 2 (Court 2)
        $matchR1C1 = GameMatch::create([
            'drawing_id' => $drawing->drawing_id,
            'court_id' => $courts[0]->court_id,
            'nomor_match' => 1,
            'status_match' => 'Stopped', // Court 1 dihentikan
            'stop_reason' => 'Hujan di Court 1',
        ]);
        $matchR1C2 = GameMatch::create([
            'drawing_id' => $drawing->drawing_id,
            'court_id' => $courts[1]->court_id,
            'nomor_match' => 2,
            'status_match' => 'In Progress', // Court 2 masih main
        ]);

        $savedScores = [
            '_meta' => ['active_round' => 'round_1'],
            'round_1_court_1' => ['status' => 'stopped', 'games_a' => 2, 'games_b' => 2],
            'round_1_court_2' => ['status' => 'in_progress', 'games_a' => 3, 'games_b' => 2],
        ];
        Cache::put("scoring.game_{$gameId}", $savedScores, now()->addHours(4));

        // 1. Coba nextRound saat Court 2 masih in_progress -> HARUS DITOLAK
        $failResp = $this->post(route('scoring.next-round'), [
            'game_id' => $gameId,
            'current_round' => 'round_1',
            'next_round' => 'round_2',
        ]);
        $failResp->assertSessionHas('error');

        // Drawing juga harus mengunci ronde 2
        $drawingCheck = $this->get(route('games.drawing', ['id' => $gameId, 'round' => 2]));
        $drawingCheck->assertOk();
        $this->assertFalse($drawingCheck->viewData('roundAccess')[2] ?? true);
        // Karena ronde 2 terkunci, activeRoundNum kembali ke 1
        $this->assertEquals(1, $drawingCheck->viewData('activeRoundNum'));

        // 2. Sekarang Court 2 selesai (completed)
        $matchR1C2->status_match = 'Completed';
        $matchR1C2->save();
        $savedScores['round_1_court_2']['status'] = 'completed';
        Cache::put("scoring.game_{$gameId}", $savedScores, now()->addHours(4));

        // 3. Submit nextRound kembali -> SEKARANG HARUS BERHASIL
        $successResp = $this->post(route('scoring.next-round'), [
            'game_id' => $gameId,
            'current_round' => 'round_1',
            'next_round' => 'round_2',
        ]);
        $successResp->assertRedirect(route('games.drawing', ['id' => $gameId, 'round' => 2]));

        // Buka drawing ronde 2 -> Terbuka dan aktif!
        $drawingSuccess = $this->get(route('games.drawing', ['id' => $gameId, 'round' => 2]));
        $drawingSuccess->assertOk();
        $this->assertTrue($drawingSuccess->viewData('roundAccess')[2]);
        $this->assertEquals(2, $drawingSuccess->viewData('activeRoundNum'));
    }

    /**
     * Skenario D: Setelah transisi, kosongkan cache fixture -> baca drawing/polling kembali -> progres tetap benar.
     */
    public function test_scenario_d_empty_cache_recovers_progress_accurately_from_database(): void
    {
        [$session, $host, $players, $drawing, $courts, $rounds] = $this->createIsolatedSession(1, 8);
        $this->actingAs($host);
        $gameId = $session->session_id;

        // Ronde 1 completed
        GameMatch::create([
            'drawing_id' => $drawing->drawing_id,
            'court_id' => $courts[0]->court_id,
            'nomor_match' => 1,
            'status_match' => 'Completed',
        ]);
        // Ronde 2 stopped
        GameMatch::create([
            'drawing_id' => $drawing->drawing_id,
            'court_id' => $courts[0]->court_id,
            'nomor_match' => 2,
            'status_match' => 'Stopped',
            'stop_reason' => 'Cedera',
        ]);

        // Host telah melangkah ke ronde 3 (tersimpan di DB dan Cache)
        DB::table('tb_session')->where('session_id', $gameId)->update(['active_round' => 'round_3']);
        Cache::put("scoring.game_{$gameId}", [
            '_meta' => ['active_round' => 'round_3'],
            'round_1' => ['status' => 'completed'],
            'round_2' => ['status' => 'stopped'],
        ], now()->addHours(4));

        // Sekarang KOSONGKAN HANYA CACHE sesi pengujian ini
        Cache::forget("scoring.game_{$gameId}");

        // Baca drawing via JSON polling
        $pollResp = $this->getJson(route('games.drawing', ['id' => $gameId, 'json' => 1]));
        $pollResp->assertOk();
        $pollData = $pollResp->json();

        // Harus mengenali ronde 1 & 2 completed, dan ronde 3 accessible & active
        $this->assertTrue($pollData['round_access']['1'] || $pollData['round_access'][1]);
        $this->assertTrue($pollData['round_access']['2'] || $pollData['round_access'][2]);
        $this->assertTrue($pollData['round_access']['3'] || $pollData['round_access'][3]);
        $this->assertEquals(3, $pollData['active_round_num']);
    }

    /**
     * Skenario E: Request nextRound ganda (retry/double-click) tidak melompati ronde.
     */
    public function test_scenario_e_double_submit_next_round_does_not_jump_rounds(): void
    {
        [$session, $host, $players, $drawing, $courts, $rounds] = $this->createIsolatedSession(1, 8);
        $this->actingAs($host);
        $gameId = $session->session_id;

        GameMatch::create([
            'drawing_id' => $drawing->drawing_id,
            'court_id' => $courts[0]->court_id,
            'nomor_match' => 1,
            'status_match' => 'Completed',
        ]);
        Cache::put("scoring.game_{$gameId}", [
            '_meta' => ['active_round' => 'round_1'],
            'round_1' => ['status' => 'completed'],
        ], now()->addHours(4));

        // Submit pertama: round 1 -> 2
        $resp1 = $this->post(route('scoring.next-round'), [
            'game_id' => $gameId,
            'current_round' => 'round_1',
            'next_round' => 'round_2',
        ]);
        $resp1->assertRedirect(route('games.drawing', ['id' => $gameId, 'round' => 2]));

        // Submit kedua dengan payload yang sama (double-click form)
        $resp2 = $this->post(route('scoring.next-round'), [
            'game_id' => $gameId,
            'current_round' => 'round_1',
            'next_round' => 'round_2',
        ]);
        // Tetap redirect ke round 2, TIDAK loncat ke round 3
        $resp2->assertRedirect(route('games.drawing', ['id' => $gameId, 'round' => 2]));

        $savedScores = Cache::get("scoring.game_{$gameId}");
        $this->assertEquals('round_2', $savedScores['_meta']['active_round']);

        // Jika mencoba bypass langsung meminta round 3 saat round 2 belum dimainkan -> ditolak
        $respBypass = $this->post(route('scoring.next-round'), [
            'game_id' => $gameId,
            'current_round' => 'round_1',
            'next_round' => 'round_3',
        ]);
        $respBypass->assertSessionHas('error');
    }

    /**
     * Skenario F: Parameter URL untuk ronde yang belum terbuka tetap ditolak.
     */
    public function test_scenario_f_url_query_param_for_locked_round_is_rejected(): void
    {
        [$session, $host, $players, $drawing, $courts, $rounds] = $this->createIsolatedSession(1, 8);
        $this->actingAs($host);
        $gameId = $session->session_id;

        // Ronde 1 baru selesai
        GameMatch::create([
            'drawing_id' => $drawing->drawing_id,
            'court_id' => $courts[0]->court_id,
            'nomor_match' => 1,
            'status_match' => 'Completed',
        ]);
        Cache::put("scoring.game_{$gameId}", [
            '_meta' => ['active_round' => 'round_2'],
            'round_1' => ['status' => 'completed'],
        ], now()->addHours(4));

        // Pengguna sengaja memasukkan ?round=99 atau ?round=3 di URL
        $respLocked = $this->get(route('games.drawing', ['id' => $gameId, 'round' => 99]));
        $respLocked->assertOk();
        // Controller harus mengabaikan 99 dan fallback ke ronde aktif yang sah (2)
        $this->assertEquals(2, $respLocked->viewData('activeRoundNum'));

        $respLocked3 = $this->get(route('games.drawing', ['id' => $gameId, 'round' => 3]));
        $respLocked3->assertOk();
        $this->assertEquals(2, $respLocked3->viewData('activeRoundNum'));
    }

    /**
     * Skenario G: Ronde terakhir terminal tidak menghasilkan ronde tambahan atau fallback ke ronde pertama.
     */
    public function test_scenario_g_last_round_terminal_does_not_overflow_or_fallback_to_round_1(): void
    {
        [$session, $host, $players, $drawing, $courts, $rounds] = $this->createIsolatedSession(1, 8);
        $this->actingAs($host);
        $gameId = $session->session_id;

        // Buat semua 3 ronde terminal
        for ($r = 1; $r <= 3; $r++) {
            GameMatch::create([
                'drawing_id' => $drawing->drawing_id,
                'court_id' => $courts[0]->court_id,
                'nomor_match' => $r,
                'status_match' => $r === 3 ? 'Stopped' : 'Completed',
            ]);
        }

        Cache::put("scoring.game_{$gameId}", [
            '_meta' => ['active_round' => 'round_3'],
            'round_1' => ['status' => 'completed'],
            'round_2' => ['status' => 'completed'],
            'round_3' => ['status' => 'stopped'],
        ], now()->addHours(4));

        // 1. Coba next-round dari ronde 3 (ronde terakhir) -> harus ditolak
        $respOverflow = $this->post(route('scoring.next-round'), [
            'game_id' => $gameId,
            'current_round' => 'round_3',
            'next_round' => 'round_4',
        ]);
        $respOverflow->assertSessionHas('warning');

        // 2. Kosongkan cache dan buka drawing
        Cache::forget("scoring.game_{$gameId}");
        $drawingLast = $this->get(route('games.drawing', ['id' => $gameId]));
        $drawingLast->assertOk();
        // Tetap di ronde 3 (ronde terakhir), BUKAN kembali ke ronde 1!
        $this->assertEquals(3, $drawingLast->viewData('activeRoundNum'));
    }

    /**
     * Skenario H: Integritas data match: skor, alasan, status Stopped, dan riwayat utuh. Mode A drawing tidak berubah.
     */
    public function test_scenario_h_data_integrity_preserved_after_navigation(): void
    {
        [$session, $host, $players, $drawing, $courts, $rounds] = $this->createIsolatedSession(1, 8);
        $this->actingAs($host);
        $gameId = $session->session_id;

        $match1 = GameMatch::create([
            'drawing_id' => $drawing->drawing_id,
            'court_id' => $courts[0]->court_id,
            'nomor_match' => 1,
            'status_match' => 'Completed',
            'hasil_pertandingan' => '6-4',
            'winner_team' => 'team_a',
        ]);
        Score::create([
            'match_id' => $match1->match_id,
            'set_number' => 1,
            'game_score_a' => 6,
            'game_score_b' => 4,
            'status_score' => 'Final',
        ]);

        $match2 = GameMatch::create([
            'drawing_id' => $drawing->drawing_id,
            'court_id' => $courts[0]->court_id,
            'nomor_match' => 2,
            'status_match' => 'In Progress',
        ]);

        Cache::put("scoring.game_{$gameId}", [
            '_meta' => ['active_round' => 'round_2'],
            'round_1' => ['status' => 'completed', 'games_a' => 6, 'games_b' => 4],
        ], now()->addHours(4));

        // Stop match 2
        $this->postJson(route('scoring.stop-match'), [
            'game_id' => $gameId,
            'match_id' => $match2->match_id,
            'match_key' => 'round_2',
            'round' => 'round_2',
            'stop_type' => 'keep_in_session',
            'reason' => 'Cedera ankle',
            'related_player_ids' => [$players[1]->player_id],
            'current_games_a' => 4,
            'current_games_b' => 3,
        ]);

        // Simpan snapshot drawing schedule sebelum navigasi
        $scheduleBefore = Cache::get("drawing.schedule_{$gameId}");

        // Lanjut ke ronde 3
        $this->post(route('scoring.next-round'), [
            'game_id' => $gameId,
            'current_round' => 'round_2',
            'next_round' => 'round_3',
        ]);

        // Buka drawing ronde 3
        $this->get(route('games.drawing', ['id' => $gameId, 'round' => 3]));

        // Verifikasi integritas match 1
        $freshM1 = $match1->fresh();
        $this->assertEquals('Completed', $freshM1->status_match);
        $this->assertEquals('6-4', $freshM1->hasil_pertandingan);
        $this->assertEquals('team_a', $freshM1->winner_team);

        // Verifikasi integritas match 2
        $freshM2 = $match2->fresh();
        $this->assertEquals('Stopped', $freshM2->status_match);
        $this->assertEquals('Cedera ankle', $freshM2->stop_reason);
        $this->assertEquals('keep_in_session', $freshM2->stop_type);
        $this->assertNull($freshM2->winner_team); // Stopped tidak memberikan kemenangan otomatis

        // Verifikasi schedule Mode A tidak berubah
        $scheduleAfter = Cache::get("drawing.schedule_{$gameId}");
        $this->assertEquals($scheduleBefore['rounds'][3], $scheduleAfter['rounds'][3]);
    }

    /**
     * Uji 1 (Spesifik Permintaan User):
     * Semua court ronde 2 terminal, host BELUM menekan Lanjut, cache fixture dikosongkan:
     * Ronde aktif tetap ronde 2, tombol lanjut tersedia.
     */
    public function test_round_2_terminal_host_not_advanced_cache_cleared_stays_on_round_2_and_next_button_available()
    {
        [$session, $host, $players, $drawing, $courts, $rounds] = $this->createIsolatedSession(2, 8);
        $gameId = $session->session_id;

        // Ronde 1: Court 1 & Court 2 Completed
        GameMatch::create([
            'drawing_id' => $drawing->drawing_id,
            'court_id' => $courts[0]->court_id,
            'nomor_match' => 1,
            'status_match' => 'Completed',
            'hasil_pertandingan' => '6-2',
            'winner_team' => 'team_a',
        ]);
        GameMatch::create([
            'drawing_id' => $drawing->drawing_id,
            'court_id' => $courts[1]->court_id,
            'nomor_match' => 2,
            'status_match' => 'Completed',
            'hasil_pertandingan' => '6-3',
            'winner_team' => 'team_a',
        ]);

        // Ronde 2: Court 1 Completed, Court 2 Stopped (Keduanya Terminal)
        GameMatch::create([
            'drawing_id' => $drawing->drawing_id,
            'court_id' => $courts[0]->court_id,
            'nomor_match' => 3,
            'status_match' => 'Completed',
            'hasil_pertandingan' => '6-4',
            'winner_team' => 'team_a',
        ]);
        GameMatch::create([
            'drawing_id' => $drawing->drawing_id,
            'court_id' => $courts[1]->court_id,
            'nomor_match' => 4,
            'status_match' => 'Stopped',
            'stop_type' => 'keep_in_session',
            'stop_reason' => 'Cedera pergelangan kaki',
            'winner_team' => null,
        ]);

        // Host BELUM menekan Lanjut -> tb_session.active_round masih null
        $this->assertNull($session->fresh()->active_round);

        // KOSONGKAN SELURUH CACHE FIXTURE
        Cache::flush();

        // 1. Drawing JSON: Ronde aktif harus tetap ronde 2
        $respDrawing = $this->actingAs($host)->get(route('games.drawing', [
            'id' => $gameId,
            'json' => 1,
        ]));
        $respDrawing->assertOk();
        $this->assertEquals(2, $respDrawing->json('active_round_num'), 'Saat host belum menekan lanjut dan cache kosong, ronde aktif harus tetap ronde 2.');
        $this->assertTrue($respDrawing->json('round_access.2'), 'Ronde 2 harus accessible.');
        $this->assertTrue($respDrawing->json('round_access.3'), 'Ronde 3 sudah berstatus unlocked/boleh diakses karena ronde 2 selesai.');

        // 2. Halaman Live Scoring: Host melihat ronde aktif adalah ronde 2 dan tombol lanjut tersedia
        $respLive = $this->actingAs($host)->get(route('scoring.live', [
            'id' => $gameId,
        ]));
        $respLive->assertOk();
        // Tombol lanjut globalNextRoundForm tersedia di HTML (tidak disembunyikan dengan class hidden)
        $respLive->assertSee('id="globalNextRoundForm"', false);
        $respLive->assertDontSee('class="hidden m-0" id="globalNextRoundForm"', false);
    }

    /**
     * Uji 2 (Spesifik Permintaan User):
     * Host SUDAH menekan Lanjut ke ronde 3, cache fixture dikosongkan:
     * Ronde aktif tetap ronde 3.
     */
    public function test_host_advanced_to_round_3_persists_to_tb_session_and_cache_cleared_stays_on_round_3()
    {
        [$session, $host, $players, $drawing, $courts, $rounds] = $this->createIsolatedSession(2, 8);
        $gameId = $session->session_id;

        // Ronde 1: Completed
        GameMatch::create([
            'drawing_id' => $drawing->drawing_id,
            'court_id' => $courts[0]->court_id,
            'nomor_match' => 1,
            'status_match' => 'Completed',
        ]);
        GameMatch::create([
            'drawing_id' => $drawing->drawing_id,
            'court_id' => $courts[1]->court_id,
            'nomor_match' => 2,
            'status_match' => 'Completed',
        ]);

        // Ronde 2: Court 1 Completed, Court 2 Stopped
        GameMatch::create([
            'drawing_id' => $drawing->drawing_id,
            'court_id' => $courts[0]->court_id,
            'nomor_match' => 3,
            'status_match' => 'Completed',
        ]);
        GameMatch::create([
            'drawing_id' => $drawing->drawing_id,
            'court_id' => $courts[1]->court_id,
            'nomor_match' => 4,
            'status_match' => 'Stopped',
            'stop_type' => 'keep_in_session',
            'stop_reason' => 'Cedera bahu',
        ]);

        // Host menekan Lanjut ke Ronde 3
        $respNext = $this->actingAs($host)->post(route('scoring.next-round'), [
            'game_id' => $gameId,
            'current_round' => 'round_2',
            'next_round' => 'round_3',
        ]);
        $respNext->assertRedirect();

        // Buktikan bahwa nextRound BENAR-BENAR menulis tb_session.active_round ke database
        $persistedInDb = DB::table('tb_session')->where('session_id', $gameId)->value('active_round');
        $this->assertEquals('round_3', $persistedInDb, 'nextRound harus menulis tb_session.active_round = round_3 setelah validasi berhasil.');

        // KOSONGKAN SELURUH CACHE FIXTURE (Simulasi cache hilang total / cold start)
        Cache::flush();

        // 1. Drawing JSON: Ronde aktif tetap ronde 3
        $respDrawing = $this->actingAs($host)->get(route('games.drawing', [
            'id' => $gameId,
            'json' => 1,
        ]));
        $respDrawing->assertOk();
        $this->assertEquals(3, $respDrawing->json('active_round_num'), 'Setelah host menekan lanjut dan cache dikosongkan, ronde aktif tetap ronde 3.');

        // 2. Halaman Live Scoring: Default URL tanpa parameter round membuka ronde 3
        $respLive = $this->actingAs($host)->get(route('scoring.live', [
            'id' => $gameId,
        ]));
        $respLive->assertOk();
        // Membuktikan live scoring berada pada ronde 3
        $respLive->assertSee('Ronde 3', false);
    }

    /**
     * Uji 3: Integritas Skor Stopped pada pemulihan database
     * Memastikan skor Stopped tetap membawa status penghentian dan tidak berubah menjadi kemenangan/seri sepihak.
     */
    public function test_stopped_match_in_official_db_score_retains_stop_status_and_awards_no_wins()
    {
        [$session, $host, $players, $drawing, $courts, $rounds] = $this->createIsolatedSession(1, 8);
        $gameId = $session->session_id;

        $match = GameMatch::create([
            'drawing_id' => $drawing->drawing_id,
            'court_id' => $courts[0]->court_id,
            'nomor_match' => 1,
            'status_match' => 'Stopped',
            'stop_type' => 'keep_in_session',
            'stop_reason' => 'Cedera hamstring',
            'stopped_by_user_id' => $host->user_id,
            'stopped_at' => now(),
            'winner_team' => null,
        ]);

        Score::create([
            'match_id' => $match->match_id,
            'set_number' => 1,
            'game_score_a' => 4,
            'game_score_b' => 2,
            'status_score' => 'Stopped',
            'version' => 3,
        ]);

        // Kosongkan cache
        Cache::flush();

        // Panggil endpoint update-score dengan request stale (misal point_change)
        $respUpdate = $this->actingAs($host)->postJson(route('scoring.update-score'), [
            'game_id' => $gameId,
            'round' => 'round_1',
            'court' => 0,
            'action' => 'point_delta',
            'team' => 'A',
            'client_version' => 2,
        ]);

        // Harus ditolak dengan status 422 dan status stopped
        $respUpdate->assertStatus(422);
        $this->assertEquals('stopped', $respUpdate->json('status'));
        $this->assertNull($respUpdate->json('winner_team'));
        $this->assertEquals('Cedera hamstring', $respUpdate->json('stop_reason'));
    }
}

