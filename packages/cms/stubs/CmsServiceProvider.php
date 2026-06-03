<?php

namespace App\Providers;

use Coda\Cms\Modules\DashboardModule;
use Coda\Cms\Modules\UserModule;
use Coda\Cms\Navigation\SidebarGroup;
use Coda\Cms\Navigation\SidebarItem;
use Coda\Cms\Registry;
use Illuminate\Support\ServiceProvider;

class CmsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $registry = app(Registry::class);

        $registry->register(new DashboardModule);
        $registry->register(new UserModule);

        $registry->setNavigation([
            SidebarGroup::make('dashboard', __('Dashboard'))->icon('layout-dashboard')->items([
                SidebarItem::make(__('Dashboard'), 'dashboard')->icon('layout-dashboard'),
            ]),
            SidebarGroup::make('users', __('Users'))->icon('users')->items([
                SidebarItem::make(__('Users'), 'user-index')->icon('user'),
            ]),
        ]);
    }
}
