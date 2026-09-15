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
        if (Schema::hasTable('tb_court') && !Schema::hasColumn('tb_court', 'tipe_court')) {
            Schema::table('tb_court', function (Blueprint $table) {
                $table->string('tipe_court')->nullable()->after('deskripsi');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('tb_court') && Schema::hasColumn('tb_court', 'tipe_court')) {
            Schema::table('tb_court', function (Blueprint $table) {
                $table->dropColumn('tipe_court');
            });
        }
    }
};
