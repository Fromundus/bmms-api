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
            'name' => 'Barangay Nutrition Scholar',
            'contact_number' => '09123456789',
            'email' => 'bns@test.com',
            'area' => 'Purok 1',
            'notes' => 'lorem ipsum',
            'password' => Hash::make("1234"),
            'role' => 'bns',
        ]);

        User::factory()->create([
            'name' => 'Barangay Health Worker',
            'contact_number' => '09987654321',
            'email' => 'bhw@test.com',
            'area' => 'Purok 1',
            'notes' => 'lorem ipsum',
            'password' => Hash::make("1234"),
            'role' => 'admin',
        ]);
    }
}
