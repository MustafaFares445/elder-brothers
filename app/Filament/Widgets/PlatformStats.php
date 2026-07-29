<?php

namespace App\Filament\Widgets;

use App\Models\Course;
use App\Models\CourseSubscription;
use App\Models\CourseVideo;
use App\Models\SubscriptionQrCode;
use App\Models\User;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class PlatformStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $startDate = $this->pageFilters['startDate'] ?? null;
        $endDate = $this->pageFilters['endDate'] ?? null;
        $courseId = $this->pageFilters['courseId'] ?? null;
        $subjectId = $this->pageFilters['subjectId'] ?? null;
        $academicYearId = $this->pageFilters['academicYearId'] ?? null;

        $students = User::query()
            ->where('is_admin', false)
            ->when($startDate, fn (Builder $query): Builder => $query->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn (Builder $query): Builder => $query->whereDate('created_at', '<=', $endDate));

        $courseScope = fn (Builder $query): Builder => $query
            ->when($courseId, fn (Builder $query): Builder => $query->whereKey($courseId))
            ->when($subjectId, fn (Builder $query): Builder => $query->where('subject_id', $subjectId))
            ->when($academicYearId, fn (Builder $query): Builder => $query->whereHas(
                'subject',
                fn (Builder $query): Builder => $query->where('academic_year_id', $academicYearId),
            ));

        $activeSubscriptions = CourseSubscription::query()
            ->where('status', 'active')
            ->whereNull('revoked_at')
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()))
            ->when($startDate, fn (Builder $query): Builder => $query->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn (Builder $query): Builder => $query->whereDate('created_at', '<=', $endDate))
            ->when($courseId, fn (Builder $query): Builder => $query->where('course_id', $courseId))
            ->when($subjectId || $academicYearId, fn (Builder $query): Builder => $query->whereHas('course', $courseScope));

        $publishedCourses = Course::query()->where('status', 'published');
        $draftCourses = Course::query()->where('status', 'draft');
        $courseScope($publishedCourses);
        $courseScope($draftCourses);

        return [
            Stat::make(__('dashboard.widgets.total_students'), (clone $students)->count()),
            Stat::make(__('dashboard.widgets.active_students'), (clone $students)->where('status', 'active')->count()),
            Stat::make(__('dashboard.widgets.suspended_students'), (clone $students)->where('status', 'suspended')->count()),
            Stat::make(__('dashboard.widgets.active_subscriptions'), (clone $activeSubscriptions)->count()),
            Stat::make(
                __('dashboard.widgets.expiring_subscriptions'),
                (clone $activeSubscriptions)
                    ->whereBetween('expires_at', [now(), now()->addDays(7)])
                    ->count(),
            ),
            Stat::make(__('dashboard.widgets.published_courses'), $publishedCourses->count()),
            Stat::make(__('dashboard.widgets.draft_courses'), $draftCourses->count()),
            Stat::make(
                __('dashboard.widgets.ready_videos'),
                CourseVideo::query()
                    ->where('status', 'ready')
                    ->when(
                        $courseId || $subjectId || $academicYearId,
                        fn (Builder $query): Builder => $query->whereHas('course', $courseScope),
                    )
                    ->count(),
            ),
            Stat::make(
                __('dashboard.widgets.failed_videos'),
                CourseVideo::query()
                    ->where('status', 'failed')
                    ->when(
                        $courseId || $subjectId || $academicYearId,
                        fn (Builder $query): Builder => $query->whereHas('course', $courseScope),
                    )
                    ->count(),
            ),
            Stat::make(
                __('dashboard.widgets.active_qr_codes'),
                SubscriptionQrCode::query()
                    ->where('status', 'active')
                    ->where('redemptions_count', 0)
                    ->where('expires_at', '>', now())
                    ->when($courseId, fn (Builder $query): Builder => $query->where('course_id', $courseId))
                    ->when(
                        $subjectId || $academicYearId,
                        fn (Builder $query): Builder => $query->whereHas('course', $courseScope),
                    )
                    ->count(),
            ),
        ];
    }
}
