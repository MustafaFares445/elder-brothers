<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;

class RegistrationChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = null;

    protected static ?int $sort = 2;

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '300px';

    public function getHeading(): ?string
    {
        return __('dashboard.widgets.registrations');
    }

    protected function getData(): array
    {
        $startDate = $this->pageFilters['startDate'] ?? now()->subMonths(5)->startOfMonth()->toDateString();
        $endDate = $this->pageFilters['endDate'] ?? now()->endOfMonth()->toDateString();

        $start = \Carbon\Carbon::parse($startDate)->startOfMonth();
        $end = \Carbon\Carbon::parse($endDate)->endOfMonth();
        $months = collect();

        for ($cursor = $start->copy(); $cursor->lte($end) && $months->count() < 24; $cursor->addMonth()) {
            $months->push($cursor->copy());
        }

        $counts = User::query()
            ->where('is_admin', false)
            ->whereBetween('created_at', [$start, $end])
            ->get(['created_at'])
            ->groupBy(fn (User $user): string => $user->created_at->format('Y-m'))
            ->map->count();

        return [
            'datasets' => [
                [
                    'label' => __('dashboard.widgets.registrations'),
                    'data' => $months->map(fn ($month): int => $counts->get($month->format('Y-m'), 0))->all(),
                ],
            ],
            'labels' => $months->map(fn ($month): string => $month->translatedFormat('M Y'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
