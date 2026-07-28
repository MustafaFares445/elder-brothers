<?php

namespace App\Filament\Pages;

use App\Jobs\SendAdminNotificationCampaign;
use App\Models\Course;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;

class SendNotification extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.send-notification';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static ?int $navigationSort = 10;

    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return __('dashboard.navigation.communication');
    }

    public static function getNavigationLabel(): string
    {
        return __('dashboard.actions.send_notification');
    }

    public function getTitle(): string
    {
        return __('dashboard.actions.send_notification');
    }

    public function mount(): void
    {
        $this->form->fill([
            'audience' => 'all_active',
            'action_type' => 'none',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('dashboard.fields.audience'))->schema([
                    Select::make('audience')
                        ->label(__('dashboard.fields.audience'))
                        ->options([
                            'all_active' => __('dashboard.audiences.all_active'),
                            'students' => __('dashboard.audiences.students'),
                            'course' => __('dashboard.audiences.course'),
                            'expiring' => __('dashboard.audiences.expiring'),
                        ])
                        ->live()
                        ->required(),
                    Select::make('student_ids')
                        ->label(__('dashboard.resources.students'))
                        ->multiple()
                        ->searchable()
                        ->options(fn (): array => User::query()->where('is_admin', false)->where('status', 'active')->orderBy('full_name')->pluck('full_name', 'id')->all())
                        ->visible(fn ($get): bool => $get('audience') === 'students')
                        ->required(fn ($get): bool => $get('audience') === 'students'),
                    Select::make('course_id')
                        ->label(__('dashboard.fields.course'))
                        ->searchable()
                        ->options(fn (): array => Course::query()->where('status', 'published')->get()->mapWithKeys(fn (Course $course): array => [$course->id => $course->localizedTitle('ar')])->all())
                        ->visible(fn ($get): bool => $get('audience') === 'course')
                        ->required(fn ($get): bool => $get('audience') === 'course'),
                    TextInput::make('expiring_days')
                        ->label(__('dashboard.fields.expiring_days'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(365)
                        ->default(7)
                        ->visible(fn ($get): bool => $get('audience') === 'expiring')
                        ->required(fn ($get): bool => $get('audience') === 'expiring'),
                ])->columns(2),
                Tabs::make('translations')->tabs([
                    Tab::make(__('dashboard.fields.arabic'))->schema([
                        TextInput::make('title_ar')->label(__('dashboard.fields.title'))->required()->maxLength(150),
                        Textarea::make('body_ar')->label(__('dashboard.fields.message'))->required()->rows(5)->maxLength(2000),
                    ]),
                    Tab::make(__('dashboard.fields.english'))->schema([
                        TextInput::make('title_en')->label(__('dashboard.fields.title'))->required()->maxLength(150),
                        Textarea::make('body_en')->label(__('dashboard.fields.message'))->required()->rows(5)->maxLength(2000),
                    ]),
                ])->columnSpanFull(),
                Section::make(__('dashboard.fields.action'))->schema([
                    Select::make('action_type')
                        ->label(__('dashboard.fields.action_type'))
                        ->options([
                            'none' => __('dashboard.actions.none'),
                            'course' => __('dashboard.resources.course'),
                            'subscription' => __('dashboard.resources.subscription'),
                            'url' => __('dashboard.fields.url'),
                        ])
                        ->live()
                        ->required(),
                    TextInput::make('action_id')
                        ->label(__('dashboard.fields.action_id'))
                        ->numeric()
                        ->visible(fn ($get): bool => in_array($get('action_type'), ['course', 'subscription'], true)),
                    TextInput::make('action_url')
                        ->label(__('dashboard.fields.url'))
                        ->url()
                        ->visible(fn ($get): bool => $get('action_type') === 'url'),
                ])->columns(2),
            ])
            ->statePath('data');
    }

    public function send(): void
    {
        $data = $this->form->getState();

        SendAdminNotificationCampaign::dispatch($data);

        Notification::make()
            ->success()
            ->title(__('dashboard.messages.notification_queued'))
            ->send();

        $this->form->fill([
            'audience' => 'all_active',
            'action_type' => 'none',
        ]);
    }
}
