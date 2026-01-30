<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('votes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('answer_id');
            $table->uuid('voter_id');
            $table->timestamps();

            $table->foreign('answer_id')->references('id')->on('answers')->cascadeOnDelete();
            $table->foreign('voter_id')->references('id')->on('players')->cascadeOnDelete();
            $table->unique(['answer_id', 'voter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('votes');
    }
};
