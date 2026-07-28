<?php

namespace App\Filament\Resources\VideoProgress;

use App\Filament\Resources\VideoProgress\Pages;
use App\Models\VideoProgress;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VideoProgressResource extends Resource
{
    protected static ?string $model = VideoProgress::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string|\UnitEnum|null $navigationGroup = null;

    public static function getNavigationGroup(): ?string
    {
        return __('dashboard.navigation.students');
    }

    public static function getNavigationLabel(): string
    {
        return __('dashboard.resources.video_progress');
    }

    public static function getModelLabel(): string
    {
        return __('dashboard.resources.video_progress');
    }

    public static function getPluralModelLabel(): string
    {
        return __('dashboard.resources.video_progress');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextEntry::make('user.full_name')->label(__('dashboard.fields.user')),
                TextEntry::make('video.course.title.ar')->label(__('dashboard.fields.course')),
                TextEntry::make('video.title.ar')->label(__('dashboard.fields.video')),
                TextEntry::make('watched_seconds')->label(__('dashboard.fields.watched_seconds'))->formatStateUsing(fn (int $state): string => gmdate('H:i:s', $state)),
                TextEntry::make('last_position_seconds')->label(__('dashboard.fields.last_position_seconds'))->formatStateUsing(fn (int $state): string => gmdate('H:i:s', $state)),
                TextEntry::make('progress_percentage')->label(__('dashboard.fields.progress_percentage'))->state(fn (VideoProgress $record): string => self::percentage($record).'%'),
                TextEntry::make('completed_at')->label(__('dashboard.fields.completed_at'))->dateTime(),
                TextEntry::make('last_watched_at')->label(__('dashboard.fields.last_watched_at'))->dateTime(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user', 'video.course.subject.academicYear']))
            ->columns([
                TextColumn::make('user.full_name')->label(__('dashboard.fields.user'))->searchable()->sortable(),
                TextColumn::make('video.course.title.ar')->label(__('dashboard.fields.course'))->searchable(),
                TextColumn::make('video.title.ar')->label(__('dashboard.fields.video'))->searchable(),
                TextColumn::make('watched_seconds')->label(__('dashboard.fields.watched_seconds'))->formatStateUsing(fn (int $state): string => gmdate('H:i:s', $state)),
                TextColumn::make('progress')->label(__('dashboard.fields.progress_percentage'))->state(fn (VideoProgress $record): string => self::percentage($record).'%')->badge(),
                IconColumn::make('completed_at')->label(__('dashboard.fields.completed'))->boolean(),
                TextColumn::make('last_watched_at')->label(__('dashboard.fields.last_watched_at'))->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('user_id')->label(__('dashboard.fields.user'))->relationship('user', 'full_name')->searchable()->preload(),
                SelectFilter::make('course_id')
                    ->label(__('dashboard.fields.course'))
                    ->options(fn (): array => \App\Models\Course::query()->get()->mapWithKeys(fn ($course): array => [$course->id => $course->localizedTitle('ar')])->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when($data['value'] ?? null, fn (Builder $query, $courseId): Builder => $query->whereHas('video', fn (Builder $query): Builder => $query->where('course_id', $courseId)))),
                Filter::make('completed')->label(__('dashboard.fields.completed'))->query(fn (Builder $query): Builder => $query->whereNotNull('completed_at')),
                Filter::make('incomplete')->label(__('dashboard.fields.incomplete'))->query(fn (Builder $query): Builder => $query->whereNull('completed_at')),
            ])
            ->recordUrl(fn (VideoProgress $record): string => static::getUrl('view', ['record' => $record]))
            ->defaultSort('last_watched_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVideoProgress::route('/'),
            'view' => Pages\ViewVideoProgress::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    private static function percentage(VideoProgress $record): int
    {
        $duration = max(1, (int) ($record->video?->duration_seconds ?? 0));

        return min(100, (int) round(((int) $record->watched_seconds / $duration) * 100));
    }
}
