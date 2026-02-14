<?php

namespace Database\Seeders;

use App\Infrastructure\Models\Player;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin user
        $admin = User::firstOrCreate(
            ['email' => 'terjen@gmail.com'],
            [
                'name' => 'Terje Nesthus',
                'nickname' => 'Godskalk',
                'email' => 'terjen@gmail.com',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ],
        );

        Player::firstOrCreate(
            ['user_id' => $admin->id],
            [
                'nickname' => 'Godskalk',
                'is_guest' => false,
                'last_active_at' => now(),
            ],
        );

        // Test user (non-admin)
        $testUser = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'nickname' => 'TestPlayer',
                'email' => 'test@example.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ],
        );

        Player::firstOrCreate(
            ['user_id' => $testUser->id],
            [
                'nickname' => 'TestPlayer',
                'is_guest' => false,
                'last_active_at' => now(),
            ],
        );
    }
}
