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
        if (Schema::hasTable('tb_venue')) {
            Schema::table('tb_venue', function (Blueprint $table) {
                if (! Schema::hasColumn('tb_venue', 'google_maps_url')) {
                    $table->text('google_maps_url')->nullable();
                }
                if (! Schema::hasColumn('tb_venue', 'sport_type')) {
                    $table->string('sport_type', 100)->nullable();
                }
                if (! Schema::hasColumn('tb_venue', 'jumlah_court')) {
                    $table->integer('jumlah_court')->nullable()->default(1);
                }
                if (! Schema::hasColumn('tb_venue', 'tipe_arena')) {
                    $table->string('tipe_arena', 100)->nullable();
                }
                if (! Schema::hasColumn('tb_venue', 'jenis_permukaan')) {
                    $table->string('jenis_permukaan', 100)->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('tb_venue')) {
            Schema::table('tb_venue', function (Blueprint $table) {
                $columnsToDrop = [];
                foreach (['google_maps_url', 'sport_type', 'jumlah_court', 'tipe_arena', 'jenis_permukaan'] as $column) {
                    if (Schema::hasColumn('tb_venue', $column)) {
                        $columnsToDrop[] = $column;
                    }
                }
                if (! empty($columnsToDrop)) {
                    $table->dropColumn($columnsToDrop);
                }
            });
        }
    }
};
