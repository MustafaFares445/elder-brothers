<?php

namespace App\Filament\Widgets;

use App\Models\Course;
use App\Models\VideoProgress;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;

class EngagementChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = null;

    protected static ?int $sort = 4;

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '320px';

    protected function getHeading(): ?string
    {
        return __('dashboard.widgets.top_watched_courses');
    }

    protected function getData(): array
    {
        $startDate = $this->pageFilters['startDate'] ?? null;
        $endDate = $this->pageFilters['endDate'] ?? null;
        $courseId = $this->pageFilters['courseId'] ?? null;
        $subjectId = $this->pageFilters['subjectId'] ?? null;
        $academicYearId = $this->pageFilters['academicYearId'] ?? null;

        $rows = VideoProgress::query()
            ->join('course_videos', 'course_videos.id', '=', 'video_progress.course_video_id')
            ->join('courses', 'courses.id', '=', 'course_videos.course_id')
            ->when($startDate, fn (Builder $query): Builder => $query->whereDate('video_progress.last_watched_at', '>=', $startDate))
            ->when($endDate, fn (Builder $query): Builder => $query->whereDate('video_progress.last_watched_at', '<=', $endDate))
            ->when($courseId, fn (Builder $query): Builder => $query->where('courses.id', $courseId))
            ->when($subjectId, fn (Builder $query): Builder => $query->where('courses.subject_id', $subjectId))
            ->when($academicYearId, fn (Builder $query): Builder => $query->whereIn('courses.subject_id', \App\Models\Subject::query()->where('academic_year_id', $academicYearId)->select('id')))
            ->selectRaw('courses.id, SUM(video_progress.watched_seconds) as watched_seconds')
            ->groupBy('courses.id')
            ->orderByDesc('watched_seconds')
            ->limit(7)
            ->get();

        $courses = Course::query()
            ->whereIn('id', $rows->pluck('id'))
            ->get()
            ->keyBy('id');

        return [
            'datasets' => [[
                'label' => __('dashboard.widgets.watch_hours'),
                'data' => $rows->map(fn ($row): float => round(((int) $row->watched_seconds) / 3600, 1))->all(),
            ]],
            'labels' => $rows->map(fn ($row): string => $courses->get($row->id)?->localizedTitle('ar') ?? (string) $row->id)->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
