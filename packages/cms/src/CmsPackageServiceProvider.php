<?php

namespace Coda\Cms;

use Coda\Cms\Auth\Actions\CreateNewUser;
use Coda\Cms\Auth\Actions\ResetUserPassword;
use Coda\Cms\Auth\Actions\UpdateUserPassword;
use Coda\Cms\Auth\Actions\UpdateUserProfileInformation;
use Coda\Cms\Console\InstallCommand;
use Coda\Cms\Http\Controllers\MediaController;
use Coda\Cms\Http\Middleware\AutoLoginInDevelopment;
use Coda\Cms\Http\Middleware\EnsureAdminAccess;
use Coda\Cms\Http\Responses\TypeAwareLoginResponse;
use Coda\Cms\MediaEditing\Editors\FocusPointEditor;
use Coda\Cms\MediaEditing\MediaEditorRegistry;
use Coda\Cms\Livewire\Auth\ChangePassword;
use Coda\Cms\Livewire\Auth\ManagePasskeys;
use Coda\Cms\Models\User;
use Coda\Cms\Livewire\CmsPage;
use Coda\Cms\Livewire\ComponentPortal;
use Coda\Cms\Livewire\Dashboard;
use Coda\Cms\Livewire\FormModal;
use Coda\Cms\Livewire\ModelDetailsPage;
use Coda\Cms\Livewire\LoginUserSwitcher;
use Coda\Cms\Livewire\MobilePreview;
use Coda\Cms\Livewire\UserList;
use Coda\Cms\Livewire\UserSwitcher;
use Coda\Cms\Models\Media;
use Coda\Cms\Models\Observers\MediaObserver;
use Coda\Cms\Support\PathGenerator\CollectionPathGenerator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Passkeys;
use Livewire\Livewire;

class CmsPackageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/cms.php', 'cms');

        config([
            'media-library.media_model' => config('cms.models.media', Media::class),
            'media-library.media_observer' => MediaObserver::class,
            'media-library.path_generator' => CollectionPathGenerator::class,
        ]);

        $this->app->singleton(Registry::class);
        $this->app->singleton(MediaEditorRegistry::class, function () {
            return (new MediaEditorRegistry)
                ->register(FocusPointEditor::key(), FocusPointEditor::class);
        });
    }

    public function boot(): void
    {
        $this->app['router']->aliasMiddleware('cms.admin', EnsureAdminAccess::class);

        if (! $this->app->isProduction() && config('cms.auto_login')) {
            $this->app['router']->pushMiddlewareToGroup('web', AutoLoginInDevelopment::class);
        }

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

        if (config('cms.auth.passkeys.enabled')) {
            $this->configurePasskeys();
        }

        Livewire::component('cms.form-modal', FormModal::class);
        Livewire::component('cms.page', CmsPage::class);
        Livewire::component('cms.component-portal', ComponentPortal::class);
        Livewire::component('cms.dashboard', Dashboard::class);
        Livewire::component('cms.model-details-page', ModelDetailsPage::class);
        Livewire::component('cms.user-list', UserList::class);

        if (config('cms.user_switching')) {
            Livewire::component('cms.user-switcher', UserSwitcher::class);
        }

        Livewire::component('cms.login-user-switcher', LoginUserSwitcher::class);

        Livewire::component('cms.mobile-preview', MobilePreview::class);

        Route::middleware(['web', 'auth'])->get('/dev/mobile-preview', MobilePreview::class)->name('cms.mobile-preview');

        Route::middleware(['web'])
            ->get('/cms/media/{media}/{filename}', [MediaController::class, 'show'])
            ->name('cms.media.show');
    }

    protected function registerAuthComponents(): void
    {
        Livewire::component('auth.change-password', ChangePassword::class);
    }

    protected function configurePasskeys(): void
    {
        if (! class_exists(Passkeys::class)) {
            return;
        }

        Passkeys::useUserModel(config('cms.models.user', User::class));

        Livewire::component('auth.manage-passkeys', ManagePasskeys::class);
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
