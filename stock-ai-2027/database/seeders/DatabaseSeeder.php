<?php

namespace Database\Seeders;

use App\Models\User;
use Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(IcbSeeder::class);

        // Tạo tài khoản admin
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@ai.com',
            'password' => Hash::make('admin@2027'),
        ]);
    }
}
