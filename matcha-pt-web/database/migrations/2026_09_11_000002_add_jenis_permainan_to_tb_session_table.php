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
                if (!Schema::hasColumn('tb_session', 'jenis_permainan')) {
                    $table->string('jenis_permainan', 20)->default('Double')->after('nama_session');
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
                if (Schema::hasColumn('tb_session', 'jenis_permainan')) {
                    $table->dropColumn('jenis_permainan');
                }
            });
        }
    }
};
