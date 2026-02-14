<?php

namespace Coda\Cms;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class CmsPackageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Registry::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'cms');

        Livewire::component('cms.form-modal', \Coda\Cms\Livewire\FormModal::class);
        Livewire::component('cms.page', \Coda\Cms\Livewire\CmsPage::class);
        Livewire::component('cms.component-portal', \Coda\Cms\Livewire\ComponentPortal::class);
    }
}
