<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('tb_session')) {
            Schema::table('tb_session', function (Blueprint $table) {
                if (!Schema::hasColumn('tb_session', 'scoring_system')) {
                    $table->string('scoring_system', 50)->default('Total of 3')->after('nama_session');
                }
            });
        }

        if (Schema::hasTable('tb_match')) {
            Schema::table('tb_match', function (Blueprint $table) {
                if (!Schema::hasColumn('tb_match', 'winner_team')) {
                    $table->string('winner_team', 50)->nullable()->after('hasil_pertandingan');
                }
            });
        }

        if (Schema::hasTable('tb_score')) {
            Schema::table('tb_score', function (Blueprint $table) {
                if (!Schema::hasColumn('tb_score', 'game_number')) {
                    $table->integer('game_number')->default(1)->after('set_number');
                }
                if (!Schema::hasColumn('tb_score', 'point_score_a')) {
                    $table->string('point_score_a', 10)->default('0')->after('game_number');
                }
                if (!Schema::hasColumn('tb_score', 'point_score_b')) {
                    $table->string('point_score_b', 10)->default('0')->after('point_score_a');
                }
                if (!Schema::hasColumn('tb_score', 'game_score_a')) {
                    $table->integer('game_score_a')->default(0)->after('point_score_b');
                }
                if (!Schema::hasColumn('tb_score', 'game_score_b')) {
                    $table->integer('game_score_b')->default(0)->after('game_score_a');
                }
                if (!Schema::hasColumn('tb_score', 'set_score_a')) {
                    $table->integer('set_score_a')->default(0)->after('game_score_b');
                }
                if (!Schema::hasColumn('tb_score', 'set_score_b')) {
                    $table->integer('set_score_b')->default(0)->after('set_score_a');
                }
                if (!Schema::hasColumn('tb_score', 'scoring_system')) {
                    $table->string('scoring_system', 50)->nullable()->after('set_score_b');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('tb_session')) {
            Schema::table('tb_session', function (Blueprint $table) {
                if (Schema::hasColumn('tb_session', 'scoring_system')) {
                    $table->dropColumn('scoring_system');
                }
            });
        }

        if (Schema::hasTable('tb_match')) {
            Schema::table('tb_match', function (Blueprint $table) {
                if (Schema::hasColumn('tb_match', 'winner_team')) {
                    $table->dropColumn('winner_team');
                }
            });
        }

        if (Schema::hasTable('tb_score')) {
            Schema::table('tb_score', function (Blueprint $table) {
                $cols = [
                    'game_number',
                    'point_score_a',
                    'point_score_b',
                    'game_score_a',
                    'game_score_b',
                    'set_score_a',
                    'set_score_b',
                    'scoring_system',
                ];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('tb_score', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
