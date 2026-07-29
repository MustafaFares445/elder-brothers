<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CatalogResources;
use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseSubscription;
use App\Models\Subject;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    use ApiResponse;

    public function home(Request $request)
    {
        $years = AcademicYear::where('is_active', true)
            ->withCount(['subjects' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($year) => CatalogResources::year($year));

        $limit = min(20, max(1, $request->integer('featured_limit', 10)));
        $courses = Course::where('status', 'published')
            ->where('is_featured', true)
            ->withCount([
                'videos' => fn ($query) => $query->where('status', 'ready'),
                'files',
            ])
            ->withSum([
                'videos as total_duration_seconds' => fn ($query) => $query->where('status', 'ready'),
            ], 'duration_seconds')
            ->latest('published_at')
            ->limit($limit)
            ->get()
            ->map(fn ($course) => CatalogResources::course($course, $request->user()));

        return $this->success([
            'user' => [
                'id' => $request->user()->id,
                'first_name' => str($request->user()->full_name)->before(' ')->toString(),
                'avatar_url' => $request->user()->avatar_path
                    ? asset('storage/'.$request->user()->avatar_path)
                    : null,
            ],
            'academic_years' => $years,
            'featured_courses' => $courses,
            'unread_notifications_count' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function academicYears(Request $request)
    {
        $query = AcademicYear::where('is_active', true)
            ->withCount(['subjects' => fn ($subjects) => $subjects->where('is_active', true)])
            ->orderBy('sort_order');

        if ($request->filled('q')) {
            $query->whereJsonContains('title->ar', $request->string('q'));
        }

        $paginator = $query->paginate(min(50, $request->integer('per_page', 15)));
        $paginator->setCollection(
            $paginator->getCollection()->map(fn ($year) => CatalogResources::year($year)),
        );

        return $this->success($paginator);
    }

    public function subjects(Request $request, AcademicYear $academicYear)
    {
        abort_unless($academicYear->is_active, 404);

        $query = $academicYear->subjects()
            ->where('is_active', true)
            ->withCount(['courses' => fn ($courses) => $courses->where('status', 'published')])
            ->orderBy('sort_order');

        if ($request->filled('q')) {
            $query->whereJsonContains('title->ar', $request->string('q'));
        }

        $paginator = $query->paginate(min(50, $request->integer('per_page', 15)));
        $paginator->setCollection(
            $paginator->getCollection()->map(fn ($subject) => CatalogResources::subject($subject)),
        );

        return $this->success([
            'academic_year' => CatalogResources::year($academicYear),
            'subjects' => $paginator->items(),
        ], meta: [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'has_more' => $paginator->hasMorePages(),
        ]);
    }

    public function subjectCourses(Request $request, Subject $subject)
    {
        abort_unless($subject->is_active, 404);

        $paginator = $this->courseQuery($request)
            ->where('subject_id', $subject->id)
            ->paginate(min(50, $request->integer('per_page', 15)));
        $paginator->setCollection(
            $paginator->getCollection()->map(fn ($course) => CatalogResources::course($course, $request->user())),
        );

        return $this->success([
            'subject' => CatalogResources::subject($subject),
            'courses' => $paginator->items(),
        ], meta: [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'has_more' => $paginator->hasMorePages(),
        ]);
    }

    public function courses(Request $request)
    {
        $paginator = $this->courseQuery($request)
            ->paginate(min(50, $request->integer('per_page', 15)));
        $paginator->setCollection(
            $paginator->getCollection()->map(fn ($course) => CatalogResources::course($course, $request->user())),
        );

        return $this->success($paginator);
    }

    public function course(Request $request, Course $course)
    {
        abort_unless($course->status === 'published', 404);

        $course
            ->load('subject.academicYear')
            ->loadCount([
                'videos' => fn ($query) => $query->where('status', 'ready'),
                'files',
            ])
            ->loadSum([
                'videos as total_duration_seconds' => fn ($query) => $query->where('status', 'ready'),
            ], 'duration_seconds');

        $subscription = $course->subscriptions()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->first();
        $hasAccess = $subscription?->isActive() ?? false;
        $progress = CatalogResources::courseProgress($course, $request->user());
        $hero = CatalogResources::media($course->hero_url);

        return $this->success(array_merge(
            CatalogResources::course($course, $request->user()),
            [
                'description' => $course->translated('description'),
                'hero_url' => $hero['url'],
                'hero_signature' => $hero['signature'],
                'hero_expires_at' => $hero['expires_at'],
                'subject' => CatalogResources::subject($course->subject),
                'academic_year' => CatalogResources::year($course->subject->academicYear),
                'subscription' => CatalogResources::subscription($subscription, $progress),
                'first_playable_video_id' => $hasAccess
                    ? $course->videos()
                        ->where('status', 'ready')
                        ->orderBy('sort_order')
                        ->value('id')
                    : null,
                'preview_videos' => [],
                'can_view_full_content' => $hasAccess,
            ],
        ));
    }

    public function myCourses(Request $request)
    {
        return $this->subscriptionList($request);
    }

    public function subscriptions(Request $request)
    {
        return $this->subscriptionList($request);
    }

    public function subscription(Request $request, CourseSubscription $subscription)
    {
        abort_unless($subscription->user_id === $request->user()->id, 404);
        $subscription->load('course');

        return $this->success(array_merge(
            CatalogResources::subscription(
                $subscription,
                CatalogResources::courseProgress($subscription->course, $request->user()),
            ),
            [
                'course' => CatalogResources::course($subscription->course, $request->user()),
                'activated_by' => 'qr',
                'redemption' => null,
                'created_at' => $subscription->created_at->toIso8601String(),
                'updated_at' => $subscription->updated_at->toIso8601String(),
            ],
        ));
    }

    private function subscriptionList(Request $request)
    {
        $query = $request->user()->subscriptions()->with('course')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $paginator = $query->paginate(min(50, $request->integer('per_page', 15)));
        $paginator->setCollection($paginator->getCollection()->map(
            fn ($subscription) => array_merge(
                CatalogResources::subscription(
                    $subscription,
                    CatalogResources::courseProgress($subscription->course, $request->user()),
                ),
                ['course' => CatalogResources::course($subscription->course, $request->user())],
            ),
        ));

        return $this->success($paginator);
    }

    private function courseQuery(Request $request)
    {
        $query = Course::where('status', 'published')
            ->withCount([
                'videos' => fn ($videos) => $videos->where('status', 'ready'),
                'files',
            ])
            ->withSum([
                'videos as total_duration_seconds' => fn ($videos) => $videos->where('status', 'ready'),
            ], 'duration_seconds');

        if ($request->filled('subject_id')) {
            $query->where('subject_id', $request->integer('subject_id'));
        }

        if ($request->filled('academic_year_id')) {
            $query->whereHas('subject', fn ($subject) => $subject
                ->where('academic_year_id', $request->integer('academic_year_id')));
        }

        if ($request->has('featured')) {
            $query->where('is_featured', $request->boolean('featured'));
        }

        if ($request->filled('q')) {
            $query->whereJsonContains('title->ar', $request->string('q'));
        }

        return $query->latest('published_at');
    }
}
