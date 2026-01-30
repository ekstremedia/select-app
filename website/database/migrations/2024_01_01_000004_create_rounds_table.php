<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rounds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('game_id');
            $table->unsignedTinyInteger('round_number');
            $table->string('acronym', 10);
            $table->enum('status', ['answering', 'voting', 'completed'])->default('answering');
            $table->timestamp('answer_deadline')->nullable();
            $table->timestamp('vote_deadline')->nullable();
            $table->timestamps();

            $table->foreign('game_id')->references('id')->on('games')->cascadeOnDelete();
            $table->unique(['game_id', 'round_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rounds');
    }
};
