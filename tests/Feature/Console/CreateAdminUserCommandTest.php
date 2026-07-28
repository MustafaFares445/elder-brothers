<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAdminUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_active_verified_admin(): void
    {
        $this->artisan('admin:create', [
            '--name' => 'مدير الاختبار',
            '--email' => 'admin@test.local',
            '--phone' => '+963944000000',
            '--password' => 'SecurePassword123!',
        ])->assertSuccessful();

        $admin = User::query()->where('email', 'admin@test.local')->firstOrFail();

        $this->assertSame('مدير الاختبار', $admin->full_name);
        $this->assertSame('+963944000000', $admin->phone);
        $this->assertSame('active', $admin->status);
        $this->assertTrue($admin->is_admin);
        $this->assertNotNull($admin->phone_verified_at);
    }

    public function test_it_rejects_an_existing_user_without_force(): void
    {
        User::factory()->create([
            'email' => 'student@test.local',
            'phone' => '+963944000001',
            'is_admin' => false,
        ]);

        $this->artisan('admin:create', [
            '--name' => 'مدير الاختبار',
            '--email' => 'student@test.local',
            '--phone' => '+963944000001',
            '--password' => 'SecurePassword123!',
        ])->assertFailed();
    }

    public function test_force_promotes_and_reactivates_an_existing_user(): void
    {
        $user = User::factory()->create([
            'full_name' => 'مستخدم قديم',
            'email' => 'old@test.local',
            'phone' => '+963944000002',
            'status' => 'suspended',
            'is_admin' => false,
            'suspended_at' => now(),
            'suspension_reason' => 'سبب تجريبي',
        ]);

        $this->artisan('admin:create', [
            '--name' => 'مدير محدث',
            '--email' => 'old@test.local',
            '--phone' => '+963944000002',
            '--password' => 'NewSecurePassword123!',
            '--force' => true,
        ])->assertSuccessful();

        $user->refresh();

        $this->assertSame('مدير محدث', $user->full_name);
        $this->assertSame('active', $user->status);
        $this->assertTrue($user->is_admin);
        $this->assertNull($user->suspended_at);
        $this->assertNull($user->suspension_reason);
    }
}
