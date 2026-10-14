<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Menambahkan kolom pelacakan penghentian pertandingan (End Match)
     * dan status partisipasi pemain per sesi mabar.
     */
    public function up(): void
    {
        if (Schema::hasTable('tb_match')) {
            Schema::table('tb_match', function (Blueprint $table) {
                if (! Schema::hasColumn('tb_match', 'stop_type')) {
                    $table->string('stop_type', 50)->nullable()->after('winner_team');
                }
                if (! Schema::hasColumn('tb_match', 'stop_reason')) {
                    $table->text('stop_reason')->nullable()->after('stop_type');
                }
                if (! Schema::hasColumn('tb_match', 'stopped_by_user_id')) {
                    $table->unsignedBigInteger('stopped_by_user_id')->nullable()->after('stop_reason');
                }
                if (! Schema::hasColumn('tb_match', 'stopped_at')) {
                    $table->dateTime('stopped_at')->nullable()->after('stopped_by_user_id');
                }
                if (! Schema::hasColumn('tb_match', 'withdrawn_player_ids')) {
                    $table->text('withdrawn_player_ids')->nullable()->after('stopped_at');
                }
                if (! Schema::hasColumn('tb_match', 'player_notes')) {
                    $table->text('player_notes')->nullable()->after('withdrawn_player_ids');
                }
            });
        }

        if (Schema::hasTable('tb_session_player')) {
            Schema::table('tb_session_player', function (Blueprint $table) {
                if (! Schema::hasColumn('tb_session_player', 'status')) {
                    $table->string('status', 30)->default('active')->after('player_id');
                }
                if (! Schema::hasColumn('tb_session_player', 'withdrawn_at')) {
                    $table->dateTime('withdrawn_at')->nullable()->after('status');
                }
                if (! Schema::hasColumn('tb_session_player', 'withdrawn_reason')) {
                    $table->text('withdrawn_reason')->nullable()->after('withdrawn_at');
                }
                if (! Schema::hasColumn('tb_session_player', 'withdrawn_match_id')) {
                    $table->unsignedBigInteger('withdrawn_match_id')->nullable()->after('withdrawn_reason');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('tb_match')) {
            Schema::table('tb_match', function (Blueprint $table) {
                $cols = [
                    'stop_type',
                    'stop_reason',
                    'stopped_by_user_id',
                    'stopped_at',
                    'withdrawn_player_ids',
                    'player_notes',
                ];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('tb_match', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        if (Schema::hasTable('tb_session_player')) {
            Schema::table('tb_session_player', function (Blueprint $table) {
                $cols = [
                    'status',
                    'withdrawn_at',
                    'withdrawn_reason',
                    'withdrawn_match_id',
                ];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('tb_session_player', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
