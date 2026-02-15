<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->uuid('kicked_by')->nullable()->after('is_co_host');
            $table->foreign('kicked_by')->references('id')->on('players')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('game_players', function (Blueprint $table) {
            $table->dropForeign(['kicked_by']);
            $table->dropColumn('kicked_by');
        });
    }
};
