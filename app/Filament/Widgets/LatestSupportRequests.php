<?php

namespace App\Filament\Widgets;

use App\Models\SupportRequest;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestSupportRequests extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('dashboard.widgets.latest_support'))
            ->query(
                SupportRequest::query()
                    ->with('user')
                    ->whereIn('status', ['open', 'in_progress'])
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('reference')->label(__('dashboard.fields.reference'))->copyable(),
                TextColumn::make('user.full_name')->label(__('dashboard.fields.user')),
                TextColumn::make('subject')->label(__('dashboard.fields.subject'))->limit(50),
                TextColumn::make('status')->label(__('dashboard.fields.status'))->badge()->formatStateUsing(fn (string $state): string => __('dashboard.statuses.'.$state)),
                TextColumn::make('created_at')->label(__('dashboard.fields.created_at'))->since(),
            ])
            ->paginated(false);
    }
}
