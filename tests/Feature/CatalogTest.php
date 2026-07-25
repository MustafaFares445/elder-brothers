<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_and_subject_course_catalog_return_seeded_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $student = User::where('phone', '+963900000002')->firstOrFail();
        Sanctum::actingAs($student);

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
