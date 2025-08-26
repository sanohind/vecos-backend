<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SuperadminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create Superadmin role if it doesn't exist
        $superadminRole = Role::firstOrCreate(['name' => 'Superadmin']);
        
        // Give superadmin all permissions
        $superadminRole->givePermissionTo(\Spatie\Permission\Models\Permission::all());

        // Create superadmin user if it doesn't exist
        $superadminUser = User::firstOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'name' => 'Superadmin User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Assign superadmin role
        $superadminUser->assignRole('Superadmin');

        $this->command->info('Superadmin role and user created successfully!');
        $this->command->info('Superadmin user: superadmin@example.com / password');
    }
}
