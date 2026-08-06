<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\AcademicYears\AcademicYearResource;
use App\Filament\Resources\Courses\CourseResource;
use App\Filament\Resources\CourseSubscriptions\CourseSubscriptionResource;
use App\Filament\Resources\Students\StudentResource;
use App\Filament\Resources\Subjects\SubjectResource;
use App\Filament\Resources\SubscriptionQrCodes\SubscriptionQrCodeResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\ExpiringSubscriptions;
use App\Filament\Widgets\PlatformStats;
use App\Filament\Widgets\ProblemVideos;
use App\Filament\Widgets\RegistrationChart;
use App\Http\Middleware\SetDashboardLocale;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): View => view('filament.video-upload-styles'),
        );
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile()
            ->brandName('الأخ الأكبر')
            ->colors([
                'primary' => '#DDB867',
                'secondary' => '#4D3922',
                'gray' => '#0D0C0D',
            ])
            ->defaultThemeMode(ThemeMode::Light)
            ->sidebarCollapsibleOnDesktop()
            ->resources([
                AcademicYearResource::class,
                SubjectResource::class,
                CourseResource::class,
                StudentResource::class,
                CourseSubscriptionResource::class,
                SubscriptionQrCodeResource::class,
                UserResource::class,
            ])
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                PlatformStats::class,
                RegistrationChart::class,
                ExpiringSubscriptions::class,
                ProblemVideos::class,
            ])
            ->navigationGroups([
                NavigationGroup::make()->label(__('dashboard.navigation.content')),
                NavigationGroup::make()->label(__('dashboard.navigation.students')),
                NavigationGroup::make()->label(__('dashboard.navigation.subscriptions')),
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
