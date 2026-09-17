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
        if (! Schema::hasTable('tb_kudos')) {
            Schema::create('tb_kudos', function (Blueprint $table) {
                $table->id('kudos_id');
                $table->unsignedBigInteger('session_id')->index();
                $table->unsignedBigInteger('giver_user_id')->nullable()->index();
                $table->unsignedBigInteger('recipient_player_id')->nullable()->index();
                $table->string('recipient_name')->index();
                $table->string('badge');
                $table->timestamps();

                $table->index(['session_id', 'recipient_name', 'badge']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_kudos');
    }
};
