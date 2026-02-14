<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hall_of_fame', function (Blueprint $table) {
            $table->id();
            $table->uuid('game_id');
            $table->string('game_code', 6);
            $table->unsignedTinyInteger('round_number');
            $table->string('acronym', 10);
            $table->string('sentence', 500);
            $table->string('author_nickname', 20);
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('votes_count')->default(0);
            $table->json('voter_nicknames')->nullable();
            $table->boolean('is_round_winner')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();
            $table->index('author_user_id');
            $table->index('votes_count');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hall_of_fame');
    }
};
