<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_verify_and_access_profile(): void
    {
        $register = $this->postJson('/api/v1/auth/register', [
            'full_name' => 'New Student',
            'phone' => '+963911111111',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $register->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('code', 'REGISTRATION_CREATED')
            ->assertJsonPath('data.verification_required', true);

        $verify = $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => '+963911111111',
            'otp' => '123456',
            'device_name' => 'phpunit',
        ]);

        $verify->assertOk()
            ->assertJsonPath('code', 'PHONE_VERIFIED')
            ->assertJsonStructure(['data' => ['access_token', 'user' => ['id', 'full_name', 'phone']]]);

        $token = $verify->json('data.access_token');

        $this->withToken($token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.user.phone', '+963911111111');
    }

    public function test_unverified_user_cannot_login(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'full_name' => 'Unverified Student',
            'phone' => '+963922222222',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ])->assertCreated();

        $this->postJson('/api/v1/auth/login', [
            'phone' => '+963922222222',
            'password' => 'Password123',
        ])->assertForbidden()->assertJsonPath('code', 'PHONE_NOT_VERIFIED');
    }
}
