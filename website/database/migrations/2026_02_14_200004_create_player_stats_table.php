<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('games_played')->default(0);
            $table->unsignedInteger('games_won')->default(0);
            $table->unsignedInteger('rounds_played')->default(0);
            $table->unsignedInteger('rounds_won')->default(0);
            $table->unsignedInteger('total_votes_received')->default(0);
            $table->unsignedInteger('total_sentences_submitted')->default(0);
            $table->string('best_sentence', 500)->nullable();
            $table->unsignedInteger('best_sentence_votes')->default(0);
            $table->decimal('win_rate', 5, 2)->default(0);
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_stats');
    }
};
