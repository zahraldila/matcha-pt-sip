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
        if (Schema::hasTable('tb_court') && !Schema::hasColumn('tb_court', 'harga_per_jam')) {
            Schema::table('tb_court', function (Blueprint $table) {
                $table->decimal('harga_per_jam', 12, 2)->default(0)->after('tipe_court');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('tb_court') && Schema::hasColumn('tb_court', 'harga_per_jam')) {
            Schema::table('tb_court', function (Blueprint $table) {
                $table->dropColumn('harga_per_jam');
            });
        }
    }
};
