<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nickname', 20)->unique()->nullable()->after('name');
            $table->string('role')->default('user')->after('remember_token');
            $table->boolean('is_banned')->default(false)->after('role');
            $table->text('ban_reason')->nullable()->after('is_banned');
            $table->timestamp('banned_at')->nullable()->after('ban_reason');
            $table->unsignedBigInteger('banned_by')->nullable()->after('banned_at');
            $table->text('two_factor_secret')->nullable()->after('banned_by');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_confirmed_at');
            $table->string('avatar_url')->nullable()->after('two_factor_recovery_codes');

            $table->foreign('banned_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['banned_by']);
            $table->dropColumn([
                'nickname',
                'role',
                'is_banned',
                'ban_reason',
                'banned_at',
                'banned_by',
                'two_factor_secret',
                'two_factor_confirmed_at',
                'two_factor_recovery_codes',
                'avatar_url',
            ]);
        });
    }
};
