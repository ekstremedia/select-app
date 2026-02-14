<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->renameColumn('display_name', 'nickname');
        });

        Schema::table('players', function (Blueprint $table) {
            $table->boolean('is_guest')->default(true)->after('nickname');
            $table->timestamp('last_active_at')->nullable()->after('total_score');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn(['is_guest', 'last_active_at']);
        });

        Schema::table('players', function (Blueprint $table) {
            $table->renameColumn('nickname', 'display_name');
        });
    }
};
