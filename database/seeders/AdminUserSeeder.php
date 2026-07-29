<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $name = env('ADMIN_NAME', 'مدير النظام');
        $phone = env('ADMIN_PHONE', '+963900000000');
        $email = env('ADMIN_EMAIL', 'admin@elder.local');
        $password = env('ADMIN_PASSWORD', 'Password123!');

        $user = User::query()
            ->where('phone', $phone)
            ->orWhere('email', $email)
            ->first() ?? new User();

        $user->fill([
            'full_name' => $name,
            'phone' => $phone,
            'email' => $email,
            'password' => $password,
            'phone_verified_at' => $user->phone_verified_at ?? now(),
            'status' => 'active',
            'is_admin' => true,
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);

        $user->save();
    }
}
