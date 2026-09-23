<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! User::where('email', 'admin@kemmytech.com')->exists()) {
            User::create([
                'name' => 'Admin',
                'email' => 'admin@kemmytech.com',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]);
        }

        if (! User::where('email', 'test@example.com')->exists()) {
            User::create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => Hash::make('password'),
            ]);
        }
    }
}
