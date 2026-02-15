<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->unsignedSmallInteger('edit_count')->default(0)->after('votes_count');
        });

        Schema::table('votes', function (Blueprint $table) {
            $table->unsignedSmallInteger('change_count')->default(0)->after('voter_nickname');
        });
    }

    public function down(): void
    {
        Schema::table('answers', function (Blueprint $table) {
            $table->dropColumn('edit_count');
        });

        Schema::table('votes', function (Blueprint $table) {
            $table->dropColumn('change_count');
        });
    }
};
