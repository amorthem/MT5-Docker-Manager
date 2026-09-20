<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'dev@localhost'],
            [
                'name' => 'Dev',
                'password' => Hash::make('46NrO,gT8s6M'),
                'role' => UserRole::Dev,
                'email_verified_at' => now(),
            ],
        );
    }
}

// Run: php artisan db:seed --class=UserSeeder
