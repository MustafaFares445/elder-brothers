<?php

namespace App\Filament\Widgets;

use App\Models\QrRedemption;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;

class QrRedemptionChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = null;

    protected static ?int $sort = 5;

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '300px';

    protected function getHeading(): ?string
    {
        return __('dashboard.widgets.qr_redemptions');
    }

    protected function getData(): array
    {
        $start = Carbon::parse($this->pageFilters['startDate'] ?? now()->subDays(29))->startOfDay();
        $end = Carbon::parse($this->pageFilters['endDate'] ?? now())->endOfDay();
        $courseId = $this->pageFilters['courseId'] ?? null;

        $days = collect();

        for ($cursor = $start->copy(); $cursor->lte($end) && $days->count() < 90; $cursor->addDay()) {
            $days->push($cursor->copy());
        }

        $counts = QrRedemption::query()
            ->when($courseId, fn (Builder $query): Builder => $query->whereHas('subscription', fn (Builder $query): Builder => $query->where('course_id', $courseId)))
            ->whereBetween('redeemed_at', [$start, $end])
            ->get(['redeemed_at'])
            ->groupBy(fn (QrRedemption $redemption): string => $redemption->redeemed_at->format('Y-m-d'))
            ->map->count();

        return [
            'datasets' => [[
                'label' => __('dashboard.widgets.qr_redemptions'),
                'data' => $days->map(fn ($day): int => $counts->get($day->format('Y-m-d'), 0))->all(),
            ]],
            'labels' => $days->map(fn ($day): string => $day->format('m-d'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
