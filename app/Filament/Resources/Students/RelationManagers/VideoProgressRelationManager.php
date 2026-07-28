<?php

namespace App\Filament\Resources\Students\RelationManagers;

use App\Models\VideoProgress;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VideoProgressRelationManager extends RelationManager
{
    protected static string $relationship = 'videoProgress';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('dashboard.resources.video_progress');
    }

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('video.course'))
            ->columns([
                TextColumn::make('video.course.title.ar')->label(__('dashboard.fields.course')),
                TextColumn::make('video.title.ar')->label(__('dashboard.fields.video')),
                TextColumn::make('watched_seconds')->label(__('dashboard.fields.watched_seconds'))->formatStateUsing(fn (int $state): string => gmdate('H:i:s', $state)),
                TextColumn::make('progress')->label(__('dashboard.fields.progress_percentage'))->state(fn (VideoProgress $record): string => min(100, (int) round(($record->watched_seconds / max(1, $record->video?->duration_seconds ?? 1)) * 100)).'%'),
                IconColumn::make('completed_at')->label(__('dashboard.fields.completed'))->boolean(),
                TextColumn::make('last_watched_at')->label(__('dashboard.fields.last_watched_at'))->dateTime(),
            ])
            ->defaultSort('last_watched_at', 'desc');
    }
}
