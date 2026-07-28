<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $name = env('ADMIN_NAME');
        $phone = env('ADMIN_PHONE');
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $name || ! $phone || ! $email || ! $password) {
            return;
        }

        User::query()->updateOrCreate(
            ['phone' => $phone],
            [
                'full_name' => $name,
                'email' => $email,
                'password' => $password,
                'phone_verified_at' => now(),
                'status' => 'active',
                'is_admin' => true,
            ],
        );
    }
}
