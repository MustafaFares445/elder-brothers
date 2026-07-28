<?php

namespace App\Filament\Resources\Courses\RelationManagers;

use App\Models\CourseFile;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CourseFilesRelationManager extends RelationManager
{
    protected static string $relationship = 'files';

    public static function getTitle($ownerRecord, string $pageClass): string
    {
        return __('dashboard.resources.course_files');
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
                        ]),
                    Tab::make(__('dashboard.fields.english'))
                        ->schema([
                            TextInput::make('title.en')
                                ->label(__('dashboard.fields.title'))
                                ->required()
                                ->extraInputAttributes(['dir' => 'ltr']),
                        ]),
                ])
                ->columnSpanFull(),
            FileUpload::make('file_path')
                ->label(__('dashboard.fields.file_path'))
                ->disk(fn () => config('filesystems.course_media', 'local'))
                ->directory(fn () => 'courses/'.$this->getOwnerRecord()->getKey().'/pdfs')
                ->visibility('private')
                ->acceptedFileTypes(['application/pdf'])
                ->maxSize(102400)
                ->storeFileNamesIn('original_name')
                ->required()
                ->columnSpanFull(),
            TextInput::make('sort_order')
                ->label(__('dashboard.fields.sort_order'))
                ->integer()
                ->minValue(0)
                ->required()
                ->default(0),
            Toggle::make('is_downloadable')
                ->label(__('dashboard.fields.downloadable'))
                ->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title.ar')
                    ->label(__('dashboard.fields.title'))
                    ->searchable(),
                TextColumn::make('original_name')
                    ->label(__('dashboard.fields.original_name'))
                    ->limit(30),
                TextColumn::make('size_bytes')
                    ->label(__('dashboard.fields.size'))
                    ->formatStateUsing(fn (int $state) => number_format($state / 1024 / 1024, 2).' MB'),
                TextColumn::make('sort_order')
                    ->label(__('dashboard.fields.sort_order'))
                    ->numeric(),
                IconColumn::make('is_downloadable')
                    ->label(__('dashboard.fields.downloadable'))
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }
}
