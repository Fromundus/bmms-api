<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Dashboard
            'dashboard.view',

            // Residents
            'residents.view',
            'residents.create',
            'residents.update',
            'residents.delete',

            // Users
            'users.view',
            'users.create',
            'users.update',
            'users.delete',

            // Reports
            'reports.view',
            'reports.create',

            // Activity Logs
            'logs.view',

            // Notifications
            'notifications.view',

            // Nutritional Guide
            'guide.view',

            // Schedule
            'schedule.view',
            'schedule.create',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $roles = [
            'admin' => [
                // Dashboard
                'dashboard.view',
                
                // Residents
                'residents.view',
                'residents.create',
                'residents.update',
                'residents.delete',

                // Reports
                'reports.view',
                'reports.create',

                // Users
                'users.view',
                'users.create',
                'users.update',
                'users.delete',

                // Activity Logs
                'logs.view',

                // Notifications
                'notifications.view',
            ],

            'bhw' => [
                // Dashboard
                'dashboard.view',

                // Users
                'users.view',
                'users.create',
                'users.update',
                'users.delete',

                // Residents
                'residents.view',
                'residents.create',
                'residents.update',
                'residents.delete',

                // Nutritional Guide
                'guide.view',

                // Notifications
                'notifications.view',
                
                // Schedule
                'schedule.view',
                'schedule.create',
            ],

            'bns' => [
                // Dashboard
                'dashboard.view',

                // Residents
                'residents.view',
                'residents.create',
                'residents.update',
                'residents.delete',

                // Nutritional Guide
                'guide.view',

                // Notifications
                'notifications.view',
                
                // Schedule
                'schedule.view',
                'schedule.create',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($rolePermissions);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
