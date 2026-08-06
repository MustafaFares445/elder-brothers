<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesLearningFixtures;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use CreatesLearningFixtures;
    use RefreshDatabase;

    public function test_home_and_subject_course_catalog_return_seeded_data(): void
    {
        $fixture = $this->createLearningFixture();
        Sanctum::actingAs($fixture['subscriber']);

        $home = $this->getJson('/api/v1/home');
        $home->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(4, 'data.academic_years')
            ->assertJsonCount(3, 'data.featured_courses');

        $years = $this->getJson('/api/v1/academic-years')->assertOk();
        $yearId = $years->json('data.0.id');

        $subjects = $this->getJson("/api/v1/academic-years/{$yearId}/subjects")->assertOk();
        $subjectId = $subjects->json('data.subjects.0.id');

        $this->getJson("/api/v1/subjects/{$subjectId}/courses")
            ->assertOk()
            ->assertJsonCount(1, 'data.courses');
    }
}
