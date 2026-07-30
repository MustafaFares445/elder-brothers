<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `users` MODIFY `status` ENUM('inactive','active','suspended') NOT NULL DEFAULT 'active'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('users')->where('status', 'inactive')->update(['status' => 'active']);
            DB::statement("ALTER TABLE `users` MODIFY `status` ENUM('active','suspended') NOT NULL DEFAULT 'active'");
        }
    }
};