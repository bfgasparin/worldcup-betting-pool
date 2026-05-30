<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Seed the application's pre-registered, passwordless users.
     *
     * These users authenticate via emailed login codes; they have no passwords.
     * Replace the placeholder entries below with the real list of users.
     */
    public function run(): void
    {
        /** @var array<int, array{name: string, email: string}> $users */
        $users = [
            // Paste the real, pre-registered users here.
            ['name' => 'Admin User', 'email' => 'admin@example.com'],
            ['name' => 'Jane Doe', 'email' => 'jane@example.com'],
            ['name' => 'John Smith', 'email' => 'john@example.com'],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                ['name' => $user['name'], 'email_verified_at' => now()],
            );
        }
    }
}
