<?php

namespace App\Filament\Resources\Courses\RelationManagers;

use App\Filament\Forms\Components\ChunkedVideoUpload;
use App\Models\CourseVideo;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
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
            Section::make()
                ->schema([
                    TextInput::make('title.ar')
                        ->label(__('dashboard.fields.title'))
                        ->required(),
                    TextInput::make('lesson_label.ar')
                        ->label(__('dashboard.fields.subtitle')),
                ])
                ->columns(2)
                ->columnSpanFull(),
            ChunkedVideoUpload::make('source_path')
                ->label(__('dashboard.fields.source_path'))
                ->courseId(fn (): int => (int) $this->getOwnerRecord()->getKey())
                ->required()
                ->columnSpanFull(),
            FileUpload::make('thumbnail_url')
                ->label(__('dashboard.fields.thumbnail_url'))
                ->disk(fn () => config('filesystems.course_media', 'local'))
                ->directory(fn () => 'courses/'.$this->getOwnerRecord()->getKey().'/video-thumbnails')
                ->visibility('private')
                ->image()
                ->maxSize(10240),
            TextInput::make('duration_seconds')
                ->label(__('dashboard.fields.duration_seconds'))
                ->integer()
                ->minValue(1)
                ->required(),
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
                TextColumn::make('status')
                    ->label(__('dashboard.fields.status'))
                    ->formatStateUsing(fn (string $state) => __("dashboard.statuses.{$state}"))
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make()->label('إضافة فيديو'),
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
