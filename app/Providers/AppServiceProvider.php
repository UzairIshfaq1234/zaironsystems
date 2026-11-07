<?php

namespace App\Providers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ProcessUtils;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Public forms: a few attempts a minute per visitor, with a daily ceiling against slow floods.
        RateLimiter::for('forms', fn (Request $request) => [
            Limit::perMinute(5)->by('forms-min:'.$request->ip()),
            Limit::perDay(30)->by('forms-day:'.$request->ip()),
        ]);
        RateLimiter::for('admin-auth', fn (Request $request) => Limit::perMinute(5)->by('admin-auth:'.$request->ip()));
        RateLimiter::for('portal-auth', fn (Request $request) => Limit::perMinute(10)->by('portal-auth:'.$request->ip()));
        RateLimiter::for('billing', fn (Request $request) => Limit::perMinute(30)->by('billing:'.$request->ip()));

        // One gate per permission key; super admins pass every check.
        Gate::before(fn ($user) => $user instanceof User && $user->isSuperAdmin() ? true : null);
        foreach (Role::catalog() as $permission) {
            Gate::define($permission, fn ($user) => $user instanceof User && $user->hasPermission($permission));
        }

        Model::shouldBeStrict(! $this->app->isProduction());
        DB::prohibitDestructiveCommands($this->app->isProduction());

        $this->registerDevCommands();
    }

    /**
     * `php artisan dev` (or start.bat) runs everything the site needs in one window: the web server on
     * APP_URL's port (website at /, admin at /admin), the email queue worker and the scheduler.
     * The Vite dev server is left out: built assets are used, so stopping `dev` never breaks the pages.
     */
    private function registerDevCommands(): void
    {
        $port = parse_url((string) config('app.url'), PHP_URL_PORT) ?: 8000;
        // The PHP running this command, so it also works where `php` is not on the PATH (e.g. WAMP).
        $php = str_contains(PHP_BINARY, ' ') ? ProcessUtils::escapeArgument(PHP_BINARY) : PHP_BINARY;

        DevCommands::withoutDefaultCommands();
        DevCommands::register("{$php} artisan serve --port={$port}", 'site');
        // queue:listen reloads the code for every job, so code changes never need a worker restart.
        DevCommands::register("{$php} artisan queue:listen --tries=3 --timeout=120", 'emails');
        DevCommands::register("{$php} artisan schedule:work", 'scheduler');
    }
}
