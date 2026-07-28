<?php

namespace App\Providers;

use App\Models\PlatformSetting;
use App\Policies\DatabaseNotificationPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());

        Gate::policy(DatabaseNotification::class, DatabaseNotificationPolicy::class);

        if (config('app.force_https')) {
            URL::forceScheme('https');
        }

        try {
            if (Schema::hasTable('platform_settings')) {
                config([
                    'app.name' => PlatformSetting::value('platform_name', config('app.name')),
                    'elder.video_completion_percentage' => PlatformSetting::value(
                        'video_completion_percentage',
                        config('elder.video_completion_percentage'),
                    ),
                    'elder.signed_url_ttl_minutes' => PlatformSetting::value(
                        'signed_url_ttl_minutes',
                        config('elder.signed_url_ttl_minutes'),
                    ),
                    'elder.registration_enabled' => PlatformSetting::value('registration_enabled', true),
                ]);
            }
        } catch (Throwable) {
            // The application must still boot before the first migration.
        }
    }
}
