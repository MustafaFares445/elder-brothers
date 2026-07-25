<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QrSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_qr_can_be_previewed_and_redeemed_once(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $code = 'ELDER-MATH-2026-365';

        $this->postJson('/api/v1/subscriptions/qr/preview', ['code' => $code])
            ->assertOk()
            ->assertJsonPath('code', 'QR_VALID')
            ->assertJsonPath('data.valid', true);

        $this->postJson('/api/v1/subscriptions/qr/redeem', [
            'code' => $code,
            'confirm' => true,
            'device_id' => 'test-device',
        ])->assertCreated()
            ->assertJsonPath('code', 'QR_REDEEMED')
            ->assertJsonPath('data.was_extended', false);

        $this->postJson('/api/v1/subscriptions/qr/redeem', [
            'code' => $code,
            'confirm' => true,
        ])->assertStatus(409)->assertJsonPath('code', 'QR_ALREADY_USED');
    }
}
