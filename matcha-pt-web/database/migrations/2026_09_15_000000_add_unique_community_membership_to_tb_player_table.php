<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tb_player', function (Blueprint $table): void {
            $table->unique(['user_id', 'community_id'], 'tb_player_user_community_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tb_player', function (Blueprint $table): void {
            $table->dropUnique('tb_player_user_community_unique');
        });
    }
};
