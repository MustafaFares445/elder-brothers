<?php

namespace App\Filament\Resources\Courses;

use App\Filament\Resources\Courses\Pages;
use App\Filament\Resources\Courses\RelationManagers\CourseFilesRelationManager;
use App\Filament\Resources\Courses\RelationManagers\CourseVideosRelationManager;
use App\Models\Course;
use App\Models\Subject;
use App\Services\CoursePublishingService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CourseResource extends Resource
{
    protected static ?string $model = Course::class;

    public static function getNavigationGroup(): ?string
    {
        return __('dashboard.navigation.content');
    }

    public static function getNavigationLabel(): string
    {
        return __('dashboard.resources.courses');
    }

    public static function getModelLabel(): string
    {
        return __('dashboard.resources.course');
    }

    public static function getPluralModelLabel(): string
    {
        return __('dashboard.resources.courses');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->schema([
                    Select::make('subject_id')
                        ->label(__('dashboard.fields.subject'))
                        ->options(fn () => Subject::query()
                            ->with('academicYear')
                            ->whereHas('academicYear')
                            ->get()
                            ->sortBy(fn (Subject $subject) => [
                                $subject->academicYear->sort_order,
                                $subject->sort_order,
                            ])
                            ->mapWithKeys(fn (Subject $subject) => [
                                $subject->id => sprintf(
                                    '%s — %s',
                                    $subject->academicYear->localized('title'),
                                    $subject->localized('title'),
                                ),
                            ]))
                        ->required()
                        ->searchable(),
                    TextInput::make('slug')
                        ->label(__('dashboard.fields.slug'))
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(191)
                        ->helperText('lowercase-kebab-case'),
                    Select::make('status')
                        ->label(__('dashboard.fields.status'))
                        ->options([
                            'draft' => __('dashboard.statuses.draft'),
                            'published' => __('dashboard.statuses.published'),
                            'archived' => __('dashboard.statuses.archived'),
                        ])
                        ->default('draft')
                        ->required(),
                    Toggle::make('is_featured')
                        ->label(__('dashboard.fields.featured')),
                    DateTimePicker::make('published_at')
                        ->label(__('dashboard.fields.published_at')),
                ])
                ->columns(2),
            Tabs::make('translations')
                ->tabs([
                    Tab::make(__('dashboard.fields.arabic'))
                        ->schema([
                            TextInput::make('title.ar')
                                ->label(__('dashboard.fields.title'))
                                ->required()
                                ->maxLength(191),
                            Textarea::make('short_description.ar')
                                ->label(__('dashboard.fields.short_description'))
                                ->rows(3)
                                ->maxLength(1000),
                            RichEditor::make('description.ar')
                                ->label(__('dashboard.fields.description'))
                                ->required()
                                ->columnSpanFull(),
                        ]),
                    Tab::make(__('dashboard.fields.english'))
                        ->schema([
                            TextInput::make('title.en')
                                ->label(__('dashboard.fields.title'))
                                ->required()
                                ->maxLength(191)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (?string $state, $set) => $set('slug', Str::slug((string) $state)))
                                ->extraInputAttributes(['dir' => 'ltr']),
                            Textarea::make('short_description.en')
                                ->label(__('dashboard.fields.short_description'))
                                ->rows(3)
                                ->maxLength(1000)
                                ->extraInputAttributes(['dir' => 'ltr']),
                            RichEditor::make('description.en')
                                ->label(__('dashboard.fields.description'))
                                ->required()
                                ->columnSpanFull()
                                ->extraAttributes(['dir' => 'ltr']),
                        ]),
                ])
                ->columnSpanFull(),
            Section::make()
                ->schema([
                    TextInput::make('thumbnail_url')
                        ->label(__('dashboard.fields.thumbnail_url'))
                        ->url()
                        ->maxLength(2048),
                    TextInput::make('hero_url')
                        ->label(__('dashboard.fields.hero_url'))
                        ->url()
                        ->maxLength(2048),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with(['subject.academicYear'])
                ->withCount(['videos', 'files', 'subscriptions']))
            ->columns([
                TextColumn::make('title.ar')
                    ->label(__('dashboard.fields.title'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title.en')
                    ->label(__('dashboard.fields.english'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('subject.title.ar')
                    ->label(__('dashboard.fields.subject'))
                    ->searchable(),
                TextColumn::make('subject.academicYear.title.ar')
                    ->label(__('dashboard.fields.academic_year'))
                    ->toggleable(),
                TextColumn::make('status')
                    ->label(__('dashboard.fields.status'))
                    ->formatStateUsing(fn (string $state) => __("dashboard.statuses.{$state}"))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'published' => 'success',
                        'archived' => 'gray',
                        default => 'warning',
                    }),
                IconColumn::make('is_featured')
                    ->label(__('dashboard.fields.featured'))
                    ->boolean(),
                TextColumn::make('videos_count')
                    ->label(__('dashboard.resources.course_videos'))
                    ->numeric(),
                TextColumn::make('files_count')
                    ->label(__('dashboard.resources.course_files'))
                    ->numeric(),
                TextColumn::make('subscriptions_count')
                    ->label(__('dashboard.resources.subscriptions'))
                    ->numeric()
                    ->toggleable(),
                TextColumn::make('published_at')
                    ->label(__('dashboard.fields.published_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('dashboard.fields.status'))
                    ->options([
                        'draft' => __('dashboard.statuses.draft'),
                        'published' => __('dashboard.statuses.published'),
                        'archived' => __('dashboard.statuses.archived'),
                    ]),
                SelectFilter::make('subject_id')
                    ->label(__('dashboard.fields.subject'))
                    ->options(fn () => Subject::query()
                        ->get()
                        ->mapWithKeys(fn (Subject $subject) => [
                            $subject->id => $subject->localized('title'),
                        ]))
                    ->searchable(),
                TernaryFilter::make('is_featured')
                    ->label(__('dashboard.fields.featured')),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('publish')
                    ->label(__('dashboard.actions.publish'))
                    ->color('success')
                    ->visible(fn (Course $record) => $record->status !== 'published')
                    ->requiresConfirmation()
                    ->action(function (Course $record): void {
                        app(CoursePublishingService::class)->publish($record);

                        Notification::make()
                            ->title(__('dashboard.actions.publish'))
                            ->success()
                            ->send();
                    }),
                Action::make('draft')
                    ->label(__('dashboard.actions.move_to_draft'))
                    ->visible(fn (Course $record) => $record->status === 'published')
                    ->requiresConfirmation()
                    ->action(fn (Course $record) => app(CoursePublishingService::class)->moveToDraft($record)),
                Action::make('archive')
                    ->label(__('dashboard.actions.archive'))
                    ->color('gray')
                    ->visible(fn (Course $record) => $record->status !== 'archived')
                    ->requiresConfirmation()
                    ->action(fn (Course $record) => app(CoursePublishingService::class)->archive($record)),
                Action::make('duplicate')
                    ->label(__('dashboard.actions.duplicate'))
                    ->icon('heroicon-o-document-duplicate')
                    ->action(function (Course $record): void {
                        $copy = $record->replicate();
                        $copy->slug = $record->slug.'-copy-'.Str::lower(Str::random(6));
                        $copy->status = 'draft';
                        $copy->is_featured = false;
                        $copy->published_at = null;
                        $copy->save();

                        Notification::make()
                            ->title(__('dashboard.messages.course_duplicated'))
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('published_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            CourseVideosRelationManager::class,
            CourseFilesRelationManager::class,
        ];
    }

    public static function canDelete($record): bool
    {
        return $record->status === 'draft'
            && ! $record->subscriptions()->exists();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourses::route('/'),
            'create' => Pages\CreateCourse::route('/create'),
            'edit' => Pages\EditCourse::route('/{record}/edit'),
        ];
    }
}
