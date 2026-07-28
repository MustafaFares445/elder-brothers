<?php

namespace App\Filament\Widgets;

use App\Models\CourseVideo;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ProblemVideos extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('dashboard.widgets.problem_videos'))
            ->query(
                CourseVideo::query()
                    ->with('course')
                    ->where(function ($query): void {
                        $query
                            ->where('status', 'failed')
                            ->orWhere(function ($query): void {
                                $query
                                    ->where('status', 'processing')
                                    ->where('updated_at', '<', now()->subHours(2));
                            });
                    })
                    ->latest('updated_at')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('title.ar')->label(__('dashboard.fields.video')),
                TextColumn::make('course.title.ar')->label(__('dashboard.fields.course')),
                TextColumn::make('status')->label(__('dashboard.fields.status'))->badge()->formatStateUsing(fn (string $state): string => __('dashboard.statuses.'.$state)),
                TextColumn::make('updated_at')->label(__('dashboard.fields.updated_at'))->since(),
            ])
            ->paginated(false);
    }
}
