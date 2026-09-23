<?php

namespace Database\Seeders;

use App\Models\Player;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds to create or update the default Admin user.
     */
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@matcha.id'],
            [
                'nama' => 'Admin Matcha',
                'no_hp' => '081200000000',
                'password' => Hash::make('123456'),
                'role' => 'admin',
                'is_host' => true,
            ]
        );

        Player::firstOrCreate(
            ['user_id' => $admin->user_id],
            [
                'nama' => 'Admin Matcha',
                'email' => 'admin@matcha.id',
                'no_hp' => '081200000000',
                'gender' => 'Male',
                'usia' => 30,
                'level' => 'Advanced',
                'rating' => 5.00,
            ]
        );
    }
}
