<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_results', function (Blueprint $table) {
            $table->string('winner_nickname', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('game_results')->whereNull('winner_nickname')->update(['winner_nickname' => 'Unknown']);

        Schema::table('game_results', function (Blueprint $table) {
            $table->string('winner_nickname', 20)->nullable(false)->change();
        });
    }
};
