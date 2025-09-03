<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();
        User::factory()->create([
            'username' => 'driver',
            'name' => 'Driver',
            'email' => 'driver@test.com',
            'password' => Hash::make("1234"),
            'role' => 'driver',
        ]);

        User::factory()->create([
            'username' => 'admin',
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make("1234"),
            'role' => 'admin',
        ]);

        User::factory()->create([
            'username' => 'superadmin',
            'name' => 'Super Admin',
            'email' => 'superadmin@test.com',
            'password' => Hash::make("1234"),
            'role' => 'superadmin',
        ]);

    }
}
