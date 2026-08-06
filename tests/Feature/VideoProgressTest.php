<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesLearningFixtures;
use Tests\TestCase;

class VideoProgressTest extends TestCase
{
    use CreatesLearningFixtures;
    use RefreshDatabase;

    public function test_subscriber_can_save_progress_and_complete_video(): void
    {
        $fixture = $this->createLearningFixture();
        $video = $fixture['subscribed_video'];
        Sanctum::actingAs($fixture['subscriber']);

        $watchedSeconds = (int) round($video->duration_seconds * 0.92);

        $this->putJson("/api/v1/videos/{$video->id}/progress", [
            'position_seconds' => $watchedSeconds,
            'duration_seconds' => $video->duration_seconds,
            'watched_seconds' => $watchedSeconds,
            'event' => 'pause',
        ])->assertOk()
            ->assertJsonPath('data.is_completed', true)
            ->assertJsonPath('code', 'PROGRESS_SAVED');
    }
}
