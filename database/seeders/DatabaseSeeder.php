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
        $this->call([
            RolePermissionSeeder::class,
            BarangaySeeder::class,
        ]);

        // User::factory(10)->create();
        $admin = User::factory()->create([
            'name' => 'Admin',
            'contact_number' => '11111111111',
            'email' => 'admin@test.com',
            'area' => '',
            'notes' => '',
            'password' => Hash::make("1234"),
            'role' => 'admin',
        ]);

        $admin->assignRole('admin');

        $bns = User::factory()->create([
            'name' => 'Barangay Nutrition Scholar',
            'contact_number' => '09123456789',
            'email' => 'bns@test.com',
            'barangay_id' => 11,
            'area' => 'Purok 1',
            'notes' => 'lorem ipsum',
            'password' => Hash::make("1234"),
            'role' => 'bns',
        ]);

        $bns->assignRole('bns');

        $bhw = User::factory()->create([
            'name' => 'Barangay Health Worker',
            'contact_number' => '09987654321',
            'email' => 'bhw@test.com',
            'barangay_id' => 11,
            'area' => 'Purok 1',
            'notes' => 'lorem ipsum',
            'password' => Hash::make("1234"),
            'role' => 'bhw',
        ]);

        $bhw->assignRole('bhw');

        $this->call([
            PatientSeeder::class,
        ]);
        
    }
}
