<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('settings');
            $table->string('password')->nullable()->after('is_public');
            $table->timestamp('started_at')->nullable()->after('total_rounds');
            $table->timestamp('finished_at')->nullable()->after('started_at');
            $table->unsignedInteger('duration_seconds')->nullable()->after('finished_at');
        });

        // Add denormalized author_nickname to answers for archive display
        Schema::table('answers', function (Blueprint $table) {
            $table->string('author_nickname', 20)->nullable()->after('text');
        });

        // Add denormalized voter_nickname to votes for archive display
        Schema::table('votes', function (Blueprint $table) {
            $table->string('voter_nickname', 20)->nullable()->after('voter_id');
        });
    }

    public function down(): void
    {
        Schema::table('games', function (Blueprint $table) {
            $table->dropColumn(['is_public', 'password', 'started_at', 'finished_at', 'duration_seconds']);
        });

        Schema::table('answers', function (Blueprint $table) {
            $table->dropColumn('author_nickname');
        });

        Schema::table('votes', function (Blueprint $table) {
            $table->dropColumn('voter_nickname');
        });
    }
};
