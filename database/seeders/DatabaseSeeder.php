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
            'name' => 'Barangay Health Worker',
            'contact_number' => '09123456789',
            'hw_id' => '1234',
            'email' => 'bhw@test.com',
            'area' => 'Purok 1',
            'notes' => 'lorem ipsum',
            'password' => Hash::make("1234"),
            'role' => 'bhw',
        ]);

        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make("1234"),
            'role' => 'admin',
        ]);
    }
}
