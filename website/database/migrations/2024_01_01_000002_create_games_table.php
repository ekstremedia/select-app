<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 6)->unique();
            $table->uuid('host_player_id');
            $table->enum('status', ['lobby', 'playing', 'voting', 'finished'])->default('lobby');
            $table->json('settings')->nullable();
            $table->unsignedTinyInteger('current_round')->default(0);
            $table->unsignedTinyInteger('total_rounds')->default(5);
            $table->timestamps();

            $table->foreign('host_player_id')->references('id')->on('players')->cascadeOnDelete();
            $table->index('code');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};
