<?php

namespace Coda\Cms;

use Coda\Cms\Auth\Actions\CreateNewUser;
use Coda\Cms\Auth\Actions\ResetUserPassword;
use Coda\Cms\Auth\Actions\UpdateUserPassword;
use Coda\Cms\Auth\Actions\UpdateUserProfileInformation;
use Coda\Cms\Console\InstallCommand;
use Coda\Cms\Http\Middleware\EnsureAdminAccess;
use Coda\Cms\Http\Responses\TypeAwareLoginResponse;
use Coda\Cms\Livewire\Auth\ChangePassword;
use Coda\Cms\Livewire\CmsPage;
use Coda\Cms\Livewire\ComponentPortal;
use Coda\Cms\Livewire\Dashboard;
use Coda\Cms\Livewire\FormModal;
use Coda\Cms\Livewire\MobilePreview;
use Coda\Cms\Livewire\UserList;
use Coda\Cms\Livewire\UserSwitcher;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;
use Livewire\Livewire;

class CmsPackageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/cms.php', 'cms');

        $this->app->singleton(Registry::class);
    }

    public function boot(): void
    {
        $this->app['router']->aliasMiddleware('cms.admin', EnsureAdminAccess::class);

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'cms');

        $this->publishes([
            __DIR__.'/../config/cms.php' => config_path('cms.php'),
        ], 'cms-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'cms-migrations');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/cms'),
        ], 'cms-views');

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class]);
        }

        if (config('cms.auth.enabled', true)) {
            $this->configureFortify();
            $this->registerAuthComponents();
        }

        Livewire::component('cms.form-modal', FormModal::class);
        Livewire::component('cms.page', CmsPage::class);
        Livewire::component('cms.component-portal', ComponentPortal::class);
        Livewire::component('cms.dashboard', Dashboard::class);

        if (config('cms.user_switching')) {
            Livewire::component('cms.user-switcher', UserSwitcher::class);
        }

        Livewire::component('cms.mobile-preview', MobilePreview::class);

        Route::middleware(['web', 'auth'])->get('/dev/mobile-preview', MobilePreview::class)->name('cms.mobile-preview');
    }

    protected function registerAuthComponents(): void
    {
        Livewire::component('auth.change-password', ChangePassword::class);
        Livewire::component('cms.user-list', UserList::class);
    }

    protected function configureFortify(): void
    {
        if (! class_exists(Fortify::class)) {
            return;
        }

        $fortify = Fortify::class;

        config(['fortify.home' => config('cms.home', '/admin/dashboard')]);

        if (config('cms.home_by_type')) {
            $this->app->singleton(LoginResponseContract::class, TypeAwareLoginResponse::class);
        }

        $fortify::createUsersUsing(CreateNewUser::class);
        $fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        $fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        $fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        $fortify::loginView(fn () => view('cms::auth.login'));
        $fortify::requestPasswordResetLinkView(fn () => view('cms::auth.forgot-password'));
        $fortify::resetPasswordView(fn (Request $request) => view('cms::auth.reset-password', ['request' => $request]));

        RateLimiter::for('login', function (Request $request) use ($fortify) {
            $throttleKey = Str::transliterate(Str::lower($request->input($fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
