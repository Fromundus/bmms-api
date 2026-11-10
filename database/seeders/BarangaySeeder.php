<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BarangaySeeder extends Seeder
{
    public function run(): void
    {
        $barangays = [
            'Atsan (District I)',
            'Balatohan',
            'Boton',
            'Buhi',
            'Dayawa',
            'J. M. Alberto',
            'Katipunan',
            'Kilikilihan',
            'Mabato',
            'Obo',
            'Pacogon',
            'Pagsangahan',
            'Pangilao',
            'Paraiso',
            'Poblacion District II',
            'Poblacion District III',
            'Progreso',
            'Salvacion (Patagan)',
            'San Juan (Aroyao)',
            'San Marcos',
            'Santa Elena (Patagan)',
            'Siay',
            'Solong',
            'Tobrehon',
        ];

        foreach ($barangays as $barangay) {
            DB::table('barangays')->updateOrInsert(
                ['name' => $barangay],
                [
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}