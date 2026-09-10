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
        Schema::table('tb_community', function (Blueprint $table) {
            $table->string('tagline')->nullable();
            $table->string('kota_homebase')->nullable();
            $table->string('target_level')->nullable();
            $table->string('status_keanggotaan')->nullable();
            $table->string('jadwal_rutin')->nullable();
            $table->string('homebase_venue')->nullable();
            $table->json('benefits')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();

            $table->foreign('created_by')->references('user_id')->on('tb_user')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tb_community', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn([
                'tagline',
                'kota_homebase',
                'target_level',
                'status_keanggotaan',
                'jadwal_rutin',
                'homebase_venue',
                'benefits',
                'created_by',
            ]);
        });
    }
};
