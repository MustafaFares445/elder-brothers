<?php

namespace App\Filament\Resources\Courses\RelationManagers;

use App\Models\CourseVideo;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CourseVideosRelationManager extends RelationManager
{
    protected static string $relationship = 'videos';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('dashboard.resources.course_videos');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('translations')
                ->tabs([
                    Tab::make(__('dashboard.fields.arabic'))
                        ->schema([
                            TextInput::make('title.ar')
                                ->label(__('dashboard.fields.title'))
                                ->required(),
                            TextInput::make('lesson_label.ar')
                                ->label(__('dashboard.fields.subtitle')),
                        ]),
                    Tab::make(__('dashboard.fields.english'))
                        ->schema([
                            TextInput::make('title.en')
                                ->label(__('dashboard.fields.title'))
                                ->required()
                                ->extraInputAttributes(['dir' => 'ltr']),
                            TextInput::make('lesson_label.en')
                                ->label(__('dashboard.fields.subtitle'))
                                ->extraInputAttributes(['dir' => 'ltr']),
                        ]),
                ])
                ->columnSpanFull(),
            FileUpload::make('source_path')
                ->label(__('dashboard.fields.source_path'))
                ->disk(fn () => config('filesystems.private', 'local'))
                ->directory('course-videos')
                ->acceptedFileTypes(['video/mp4', 'application/vnd.apple.mpegurl'])
                ->required()
                ->columnSpanFull(),
            TextInput::make('hls_manifest_path')
                ->label(__('dashboard.fields.hls_manifest_path'))
                ->maxLength(2048),
            TextInput::make('thumbnail_url')
                ->label(__('dashboard.fields.thumbnail_url'))
                ->url()
                ->maxLength(2048),
            TextInput::make('duration_seconds')
                ->label(__('dashboard.fields.duration_seconds'))
                ->integer()
                ->minValue(1)
                ->required(),
            TextInput::make('sort_order')
                ->label(__('dashboard.fields.sort_order'))
                ->integer()
                ->minValue(0)
                ->required()
                ->default(0),
            Toggle::make('is_preview')
                ->label(__('dashboard.fields.preview')),
            Toggle::make('is_downloadable')
                ->label(__('dashboard.fields.downloadable')),
            Select::make('status')
                ->label(__('dashboard.fields.status'))
                ->options([
                    'processing' => __('dashboard.statuses.processing'),
                    'ready' => __('dashboard.statuses.ready'),
                    'failed' => __('dashboard.statuses.failed'),
                ])
                ->default('processing')
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title.ar')
                    ->label(__('dashboard.fields.title'))
                    ->searchable(),
                TextColumn::make('duration_seconds')
                    ->label(__('dashboard.fields.duration_seconds'))
                    ->formatStateUsing(fn (int $state) => gmdate('H:i:s', $state)),
                TextColumn::make('sort_order')
                    ->label(__('dashboard.fields.sort_order'))
                    ->numeric(),
                IconColumn::make('is_preview')
                    ->label(__('dashboard.fields.preview'))
                    ->boolean(),
                IconColumn::make('is_downloadable')
                    ->label(__('dashboard.fields.downloadable'))
                    ->boolean(),
                TextColumn::make('status')
                    ->label(__('dashboard.fields.status'))
                    ->formatStateUsing(fn (string $state) => __("dashboard.statuses.{$state}"))
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('mark_ready')
                    ->label(__('dashboard.actions.mark_ready'))
                    ->visible(fn (CourseVideo $record) => $record->status !== 'ready')
                    ->action(fn (CourseVideo $record) => $record->update(['status' => 'ready'])),
                DeleteAction::make()
                    ->visible(fn (CourseVideo $record) => ! $record->progress()->exists()),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }
}
