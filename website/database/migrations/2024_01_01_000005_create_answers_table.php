<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('round_id');
            $table->uuid('player_id');
            $table->string('text', 500);
            $table->unsignedInteger('votes_count')->default(0);
            $table->timestamps();

            $table->foreign('round_id')->references('id')->on('rounds')->cascadeOnDelete();
            $table->foreign('player_id')->references('id')->on('players')->cascadeOnDelete();
            $table->unique(['round_id', 'player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('answers');
    }
};
