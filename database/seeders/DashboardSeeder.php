<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DashboardSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PlatformSettingSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
