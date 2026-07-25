<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_subscriber_cannot_request_protected_video_url(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::factory()->create();
        $student = User::where('phone', '+963900000002')->firstOrFail();
        $course = $student->subscriptions()->where('status', 'active')->firstOrFail()->course;
        $video = $course->videos()->where('is_preview', false)->firstOrFail();

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/videos/{$video->id}/playback-url")
            ->assertForbidden()
            ->assertJsonPath('code', 'SUBSCRIPTION_REQUIRED');
    }
}
