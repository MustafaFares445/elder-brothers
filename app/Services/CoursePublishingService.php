<?php

namespace App\Services;

use App\Models\Course;
use Illuminate\Validation\ValidationException;

class CoursePublishingService
{
    public function publish(Course $course): Course
    {
        $course->loadMissing('subject.academicYear');

        $errors = [];

        foreach (['ar', 'en'] as $locale) {
            if (blank(data_get($course->title, $locale))) {
                $errors["title.{$locale}"][] = __('dashboard.validation.translation_required');
            }

            if (blank(data_get($course->description, $locale))) {
                $errors["description.{$locale}"][] = __('dashboard.validation.translation_required');
            }
        }

        if (! $course->subject?->is_active || ! $course->subject?->academicYear?->is_active) {
            $errors['subject_id'][] = __('dashboard.validation.active_subject_required');
        }

        if (! $course->videos()->where('status', 'ready')->exists() && ! $course->files()->exists()) {
            $errors['content'][] = __('dashboard.validation.course_content_required');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $course->forceFill([
            'status' => 'published',
            'published_at' => $course->published_at ?? now(),
        ])->save();

        return $course->refresh();
    }

    public function moveToDraft(Course $course): Course
    {
        $course->forceFill([
            'status' => 'draft',
            'published_at' => null,
        ])->save();

        return $course->refresh();
    }

    public function archive(Course $course): Course
    {
        $course->forceFill([
            'status' => 'archived',
            'is_featured' => false,
        ])->save();

        return $course->refresh();
    }
}
