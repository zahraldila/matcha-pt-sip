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
        if (Schema::hasTable('tb_score')) {
            Schema::table('tb_score', function (Blueprint $table) {
                if (!Schema::hasColumn('tb_score', 'version')) {
                    $table->integer('version')->default(0)->after('status_score');
                }
                if (!Schema::hasColumn('tb_score', 'last_event_id')) {
                    $table->string('last_event_id', 100)->nullable()->after('version');
                }
            });
        }

        if (Schema::hasTable('tb_match')) {
            Schema::table('tb_match', function (Blueprint $table) {
                if (!Schema::hasColumn('tb_match', 'version')) {
                    $table->integer('version')->default(0)->after('winner_team');
                }
                if (!Schema::hasColumn('tb_match', 'last_event_id')) {
                    $table->string('last_event_id', 100)->nullable()->after('version');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('tb_score')) {
            Schema::table('tb_score', function (Blueprint $table) {
                if (Schema::hasColumn('tb_score', 'last_event_id')) {
                    $table->dropColumn('last_event_id');
                }
                if (Schema::hasColumn('tb_score', 'version')) {
                    $table->dropColumn('version');
                }
            });
        }

        if (Schema::hasTable('tb_match')) {
            Schema::table('tb_match', function (Blueprint $table) {
                if (Schema::hasColumn('tb_match', 'last_event_id')) {
                    $table->dropColumn('last_event_id');
                }
                if (Schema::hasColumn('tb_match', 'version')) {
                    $table->dropColumn('version');
                }
            });
        }
    }
};
