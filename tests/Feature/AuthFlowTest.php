<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_user_stays_inactive_until_dashboard_activation(): void
    {
        $register = $this->postJson('/api/v1/auth/register', [
            'full_name' => 'New Student',
            'phone' => '+963911111111',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $register
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('code', 'REGISTRATION_PENDING_ACTIVATION')
            ->assertJsonPath('data.status', 'inactive')
            ->assertJsonPath('data.activation_required', true);

        $user = User::query()->where('phone', '+963911111111')->firstOrFail();
        $this->assertSame('inactive', $user->status);
        $this->assertFalse($user->account_active);
        $this->assertFalse($user->toArray()['account_active']);

        $this->postJson('/api/v1/auth/login', [
            'phone' => '+963911111111',
            'password' => 'Password123',
        ])->assertForbidden()->assertJsonPath('code', 'ACCOUNT_INACTIVE');

        $user->update(['account_active' => true]);
        $user->refresh();

        $this->assertSame('active', $user->status);
        $this->assertTrue($user->account_active);
        $this->assertTrue($user->toArray()['account_active']);

        $login = $this->postJson('/api/v1/auth/login', [
            'phone' => '+963911111111',
            'password' => 'Password123',
            'device_name' => 'phpunit',
        ]);

        $login
            ->assertOk()
            ->assertJsonPath('code', 'LOGGED_IN')
            ->assertJsonPath('data.user.status', 'active')
            ->assertJsonPath('data.user.is_active', true)
            ->assertJsonMissingPath('data.user.phone_verified')
            ->assertJsonStructure(['data' => ['access_token', 'user' => ['id', 'full_name', 'phone']]]);

        $this->withToken($login->json('data.access_token'))
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.user.phone', '+963911111111');
    }

    public function test_inactive_form_value_preserves_suspended_status_and_metadata(): void
    {
        $suspendedAt = now()->subHour()->startOfSecond();
        $user = User::query()->create([
            'full_name' => 'Suspended Student',
            'phone' => '+963933333333',
            'password' => 'Password123',
            'status' => 'suspended',
            'suspended_at' => $suspendedAt,
            'suspension_reason' => 'Policy violation',
        ]);

        $user->update(['account_active' => false]);
        $user->refresh();

        $this->assertSame('suspended', $user->status);
        $this->assertTrue($suspendedAt->equalTo($user->suspended_at));
        $this->assertSame('Policy violation', $user->suspension_reason);
    }

    public function test_registration_verification_endpoint_is_removed(): void
    {
        $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => '+963922222222',
            'otp' => '123456',
        ])->assertNotFound();
    }
}
