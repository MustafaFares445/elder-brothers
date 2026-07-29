<?php

namespace App\Filament\Widgets;

use App\Models\CourseSubscription;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class ExpiringSubscriptions extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('dashboard.widgets.expiring_subscriptions'))
            ->query(
                CourseSubscription::query()
                    ->with(['user', 'course'])
                    ->where('status', 'active')
                    ->whereNull('revoked_at')
                    ->whereBetween('expires_at', [now(), now()->addDays(7)])
            )
            ->columns([
                TextColumn::make('user.full_name')
                    ->label(__('dashboard.fields.user')),
                TextColumn::make('course.title.ar')
                    ->label(__('dashboard.fields.course')),
                TextColumn::make('expires_at')
                    ->label(__('dashboard.fields.expires_at'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('days_remaining')
                    ->label(__('dashboard.fields.days_remaining'))
                    ->state(fn (CourseSubscription $record): int => max(0, (int) now()->diffInDays($record->expires_at, false)))
                    ->badge()
                    ->color(fn (int $state): string => $state <= 3 ? 'danger' : 'warning'),
            ])
            ->emptyStateIcon('heroicon-o-clock')
            ->emptyStateHeading(__('dashboard_empty.expiring_subscriptions'))
            ->emptyStateDescription(__('dashboard_empty.expiring_subscriptions_description'))
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->limit(10))
            ->defaultSort('expires_at')
            ->paginated(false);
    }
}
