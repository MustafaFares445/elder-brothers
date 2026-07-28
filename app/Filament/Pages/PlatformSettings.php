<?php

namespace App\Filament\Pages;

use App\Models\PlatformSetting;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;

class PlatformSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.platform-settings';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 20;

    public ?array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return __('dashboard.navigation.system');
    }

    public static function getNavigationLabel(): string
    {
        return __('dashboard.resources.settings');
    }

    public function getTitle(): string
    {
        return __('dashboard.resources.settings');
    }

    public function mount(): void
    {
        $this->form->fill([
            'platform_name' => PlatformSetting::value('platform_name', config('app.name')),
            'support_contact' => PlatformSetting::value('support_contact', ''),
            'video_completion_percentage' => PlatformSetting::value('video_completion_percentage', (int) config('elder.video_completion_percentage', 90)),
            'signed_url_ttl_minutes' => PlatformSetting::value('signed_url_ttl_minutes', (int) config('elder.signed_url_ttl_minutes', 15)),
            'default_qr_duration_days' => PlatformSetting::value('default_qr_duration_days', 365),
            'default_qr_max_redemptions' => PlatformSetting::value('default_qr_max_redemptions', 1),
            'registration_enabled' => PlatformSetting::value('registration_enabled', true),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('dashboard.sections.general'))->schema([
                    TextInput::make('platform_name')->label(__('dashboard.settings.platform_name'))->required()->maxLength(100),
                    TextInput::make('support_contact')->label(__('dashboard.settings.support_contact'))->maxLength(191),
                ])->columns(2),
                Section::make(__('dashboard.sections.media'))->schema([
                    TextInput::make('video_completion_percentage')->label(__('dashboard.settings.video_completion_percentage'))->numeric()->minValue(50)->maxValue(100)->required(),
                    TextInput::make('signed_url_ttl_minutes')->label(__('dashboard.settings.signed_url_ttl_minutes'))->numeric()->minValue(1)->maxValue(1440)->required(),
                ])->columns(2),
                Section::make(__('dashboard.sections.subscriptions'))->schema([
                    TextInput::make('default_qr_duration_days')->label(__('dashboard.settings.default_qr_duration_days'))->numeric()->minValue(1)->maxValue(3650)->required(),
                    TextInput::make('default_qr_max_redemptions')->label(__('dashboard.settings.default_qr_max_redemptions'))->numeric()->minValue(1)->maxValue(100000)->required(),
                ])->columns(2),
                Section::make(__('dashboard.sections.availability'))->schema([
                    Toggle::make('registration_enabled')->label(__('dashboard.settings.registration_enabled')),
                ])->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $definitions = [
            'platform_name' => ['string', 'general'],
            'support_contact' => ['string', 'general'],
            'video_completion_percentage' => ['integer', 'media'],
            'signed_url_ttl_minutes' => ['integer', 'media'],
            'default_qr_duration_days' => ['integer', 'subscriptions'],
            'default_qr_max_redemptions' => ['integer', 'subscriptions'],
            'registration_enabled' => ['boolean', 'availability'],
        ];

        foreach ($definitions as $key => [$type, $group]) {
            PlatformSetting::put($key, $data[$key] ?? null, $type, $group);
        }

        Notification::make()
            ->success()
            ->title(__('dashboard.messages.saved'))
            ->send();
    }
}
