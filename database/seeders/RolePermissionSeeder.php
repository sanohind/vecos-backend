<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Vehicle permissions
            'view vehicles',
            'create vehicles',
            'update vehicles',
            'delete vehicles',
            
            // Booking permissions
            'view bookings',
            'create bookings',
            'update bookings', 
            'delete bookings',
            'approve bookings',
            'reject bookings',
            
            // User management permissions
            'view users',
            'create users',
            'update users',
            'delete users',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions
        
        // Superadmin role - full access including user management
        $superadminRole = Role::create(['name' => 'Superadmin']);
        $superadminRole->givePermissionTo(Permission::all());
        
        // Admin role - full access to vehicles and bookings
        $adminRole = Role::create(['name' => 'Admin']);
        $adminRole->givePermissionTo([
            'view vehicles',
            'create vehicles',
            'update vehicles',
            'delete vehicles',
            'view bookings',
            'create bookings',
            'update bookings',
            'delete bookings',
            'approve bookings',
            'reject bookings',
        ]);

        // User role - limited access
        $userRole = Role::create(['name' => 'User']);
        $userRole->givePermissionTo([
            'view vehicles',
            'view bookings',
            'create bookings',
            'update bookings', // Only their own bookings
            'delete bookings', // Only their own bookings
        ]);

        // Create demo users
        
        // Superadmin user
        $superadminUser = User::create([
            'name' => 'Superadmin User',
            'email' => 'superadmin@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $superadminUser->assignRole('Superadmin');

        // Admin user
        $adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
        $adminUser->assignRole('Admin');

        // Regular user
        $regularUser = User::create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => Hash::make('password'),
            'department' => 'Marketing',
            'nik' => '123456789',
            'email_verified_at' => now(),
        ]);
        $regularUser->assignRole('User');

        $this->command->info('Roles and permissions seeded successfully!');
        $this->command->info('Superadmin user: superadmin@example.com / password');
        $this->command->info('Admin user: admin@example.com / password');
        $this->command->info('Regular user: user@example.com / password');
    }
}