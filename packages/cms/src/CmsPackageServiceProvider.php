<?php

namespace Coda\Cms;

use Coda\Cms\Auth\Actions\CreateNewUser;
use Coda\Cms\Auth\Actions\ResetUserPassword;
use Coda\Cms\Auth\Actions\UpdateUserPassword;
use Coda\Cms\Auth\Actions\UpdateUserProfileInformation;
use Coda\Cms\Console\InstallCommand;
use Coda\Cms\Livewire\Auth\ChangePassword;
use Coda\Cms\Livewire\CmsPage;
use Coda\Cms\Livewire\ComponentPortal;
use Coda\Cms\Livewire\Dashboard;
use Coda\Cms\Livewire\FormModal;
use Coda\Cms\Livewire\UserList;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
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

        $this->configureFortify();

        Livewire::component('cms.form-modal', FormModal::class);
        Livewire::component('cms.page', CmsPage::class);
        Livewire::component('cms.component-portal', ComponentPortal::class);
        Livewire::component('auth.change-password', ChangePassword::class);
        Livewire::component('cms.user-list', UserList::class);
        Livewire::component('cms.dashboard', Dashboard::class);
    }

    protected function configureFortify(): void
    {
        config(['fortify.home' => config('cms.home', '/admin/dashboard')]);

        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::loginView(fn () => view('cms::auth.login'));
        Fortify::requestPasswordResetLinkView(fn () => view('cms::auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('cms::auth.reset-password', ['request' => $request]));

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
