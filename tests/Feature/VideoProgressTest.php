<?php

namespace Tests\Feature;

use App\Models\CourseSubscription;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VideoProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscriber_can_save_progress_and_complete_video(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('phone', '+963900000002')->firstOrFail();
        $subscription = CourseSubscription::where('user_id', $user->id)->where('status', 'active')->firstOrFail();
        $video = $subscription->course->videos()->where('is_preview', false)->firstOrFail();
        Sanctum::actingAs($user);

        $this->putJson("/api/v1/videos/{$video->id}/progress", [
            'position_seconds' => (int) round($video->duration_seconds * 0.92),
            'duration_seconds' => $video->duration_seconds,
            'watched_seconds' => (int) round($video->duration_seconds * 0.92),
            'event' => 'pause',
        ])->assertOk()
            ->assertJsonPath('data.is_completed', true)
            ->assertJsonPath('code', 'PROGRESS_UPDATED');
    }
}
