<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesLearningFixtures;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use CreatesLearningFixtures;
    use RefreshDatabase;

    public function test_non_subscriber_cannot_request_protected_video_url(): void
    {
        $fixture = $this->createLearningFixture();

        Sanctum::actingAs($fixture['active_user']);

        $this->postJson("/api/v1/videos/{$fixture['subscribed_video']->id}/playback-url")
            ->assertForbidden()
            ->assertJsonPath('code', 'FORBIDDEN');
    }
}
