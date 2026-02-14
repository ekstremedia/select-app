<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_results', function (Blueprint $table) {
            $table->id();
            $table->uuid('game_id')->unique();
            $table->string('winner_nickname', 20);
            $table->foreignId('winner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('final_scores');
            $table->unsignedTinyInteger('rounds_played');
            $table->unsignedTinyInteger('player_count');
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();
            $table->index('winner_user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_results');
    }
};
