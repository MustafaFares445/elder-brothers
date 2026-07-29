<?php

namespace App\Filament\Widgets;

use App\Models\CourseSubscription;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionSourcesChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = null;

    protected static ?int $sort = 3;

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '300px';

    public function getHeading(): ?string
    {
        return __('dashboard.widgets.subscription_sources');
    }

    protected function getData(): array
    {
        $startDate = $this->pageFilters['startDate'] ?? null;
        $endDate = $this->pageFilters['endDate'] ?? null;
        $courseId = $this->pageFilters['courseId'] ?? null;
        $subjectId = $this->pageFilters['subjectId'] ?? null;
        $academicYearId = $this->pageFilters['academicYearId'] ?? null;

        $counts = CourseSubscription::query()
            ->when($startDate, fn (Builder $query): Builder => $query->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn (Builder $query): Builder => $query->whereDate('created_at', '<=', $endDate))
            ->when($courseId, fn (Builder $query): Builder => $query->where('course_id', $courseId))
            ->when($subjectId, fn (Builder $query): Builder => $query->whereHas('course', fn (Builder $query): Builder => $query->where('subject_id', $subjectId)))
            ->when($academicYearId, fn (Builder $query): Builder => $query->whereHas('course.subject', fn (Builder $query): Builder => $query->where('academic_year_id', $academicYearId)))
            ->selectRaw('source, COUNT(*) as aggregate')
            ->groupBy('source')
            ->pluck('aggregate', 'source');

        return [
            'datasets' => [
                [
                    'label' => __('dashboard.widgets.subscription_sources'),
                    'data' => [
                        (int) $counts->get('qr', 0),
                        (int) $counts->get('admin', 0),
                    ],
                ],
            ],
            'labels' => [
                __('dashboard.statuses.qr'),
                __('dashboard.statuses.admin'),
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
