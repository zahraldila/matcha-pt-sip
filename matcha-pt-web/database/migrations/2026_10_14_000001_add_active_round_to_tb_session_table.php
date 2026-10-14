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
                if (! Schema::hasColumn('tb_session', 'active_round')) {
                    $table->string('active_round', 32)->nullable()->after('status_session');
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
                if (Schema::hasColumn('tb_session', 'active_round')) {
                    $table->dropColumn('active_round');
                }
            });
        }
    }
};
