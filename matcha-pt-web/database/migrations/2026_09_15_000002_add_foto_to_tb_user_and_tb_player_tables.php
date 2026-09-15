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
        if (Schema::hasTable('tb_user') && ! Schema::hasColumn('tb_user', 'foto')) {
            Schema::table('tb_user', function (Blueprint $table) {
                $table->string('foto', 500)->nullable()->after('role');
            });
        }

        if (Schema::hasTable('tb_player') && ! Schema::hasColumn('tb_player', 'foto')) {
            Schema::table('tb_player', function (Blueprint $table) {
                $table->string('foto', 500)->nullable()->after('email');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('tb_user') && Schema::hasColumn('tb_user', 'foto')) {
            Schema::table('tb_user', function (Blueprint $table) {
                $table->dropColumn('foto');
            });
        }

        if (Schema::hasTable('tb_player') && Schema::hasColumn('tb_player', 'foto')) {
            Schema::table('tb_player', function (Blueprint $table) {
                $table->dropColumn('foto');
            });
        }
    }
};
