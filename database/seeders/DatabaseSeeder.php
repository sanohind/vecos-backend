<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Jalankan RolePermissionSeeder dulu (penting untuk urutan)
        $this->call([
            RolePermissionSeeder::class,
        ]);

        // Factory users untuk testing (opsional)
        // User::factory(10)->create();

        // Test user tambahan jika diperlukan
        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}