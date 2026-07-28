<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\PlatformSettings;
use App\Filament\Pages\SendNotification;
use App\Filament\Widgets\EngagementChart;
use App\Filament\Widgets\ExpiringSubscriptions;
use App\Filament\Widgets\LatestSupportRequests;
use App\Filament\Widgets\PlatformStats;
use App\Filament\Widgets\ProblemVideos;
use App\Filament\Widgets\QrRedemptionChart;
use App\Filament\Widgets\RegistrationChart;
use App\Filament\Widgets\SubscriptionSourcesChart;
use App\Http\Middleware\SetDashboardLocale;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile()
            ->brandName(__('dashboard.brand'))
            ->colors([
                'primary' => Color::Amber,
            ])
            ->defaultThemeMode(ThemeMode::Light)
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(
                in: app_path('Filament/Resources'),
                for: 'App\\Filament\\Resources',
            )
            ->pages([
                Dashboard::class,
                SendNotification::class,
                PlatformSettings::class,
            ])
            ->widgets([
                PlatformStats::class,
                RegistrationChart::class,
                SubscriptionSourcesChart::class,
                EngagementChart::class,
                QrRedemptionChart::class,
                ExpiringSubscriptions::class,
                LatestSupportRequests::class,
                ProblemVideos::class,
            ])
            ->navigationGroups([
                NavigationGroup::make()->label(__('dashboard.navigation.content')),
                NavigationGroup::make()->label(__('dashboard.navigation.students')),
                NavigationGroup::make()->label(__('dashboard.navigation.subscriptions')),
                NavigationGroup::make()->label(__('dashboard.navigation.communication')),
                NavigationGroup::make()->label(__('dashboard.navigation.system')),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                SetDashboardLocale::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
