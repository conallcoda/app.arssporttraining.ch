<?php

namespace App\Providers;

use App\Cms\Modules\AthleteGroupModule;
use App\Cms\Modules\AthleteModule;
use App\Cms\Modules\CalendarModule;
use App\Cms\Modules\CategoryListModule;
use App\Cms\Modules\CategoryModule;
use App\Cms\Modules\EquipmentModule;
use App\Cms\Modules\ExerciseExternalModule;
use App\Cms\Modules\ExerciseModule;
use App\Cms\Modules\ExercisePlanModule;
use App\Cms\Modules\ExerciseProgramModule;
use App\Cms\Modules\ExerciseTemplateModule;
use App\Cms\Modules\ModifiersModule;
use Coda\Cms\Navigation\SidebarGroup;
use Coda\Cms\Navigation\SidebarItem;
use Coda\Cms\Navigation\Tab;
use Coda\Cms\Registry;
use Illuminate\Support\ServiceProvider;

class CmsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $registry = app(Registry::class);

        $registry->register(new AthleteModule);
        $registry->register(new AthleteGroupModule);
        $registry->register(new ExerciseModule);
        $registry->register(new ExerciseTemplateModule);
        $registry->register(new CategoryModule);
        $registry->register(new EquipmentModule);
        $registry->register(new ModifiersModule);
        $registry->register(new ExerciseExternalModule);
        $registry->register(new ExercisePlanModule);
        $registry->register(new ExerciseProgramModule);
        $registry->register(new CategoryListModule);
        $registry->register(new CalendarModule);

        $registry->setNavigation([
            SidebarGroup::make('database', 'Database')->icon('database')->items([
                SidebarItem::make('Athletes', 'athlete-index')->icon('users'),
                SidebarItem::make('Groups', 'athlete-group-index')->icon('users'),
            ]),
            SidebarGroup::make('training', 'Training')->icon('trophy')->items([
                SidebarItem::make('Exercises', 'exercise-index')->icon('dumbbell')->tabs([
                    Tab::make('Exercises', 'exercise-index'),
                    Tab::make('Templates', 'exercise-template-index'),
                    Tab::make('Categories', 'category-index'),
                    Tab::make('Equipment', 'equipment-index'),
                    Tab::make('Modifiers', 'modifiers-index'),
                    Tab::make('Import', 'exercise-external-index'),
                ]),
                SidebarItem::make('Programs', 'exercise-program-index')->icon('layout-list')->tabs([
                    Tab::make('Programs', 'exercise-program-index'),
                    Tab::make('Plans', 'exercise-plan-index'),
                ]),
                SidebarItem::make('Categories', 'category-list-index')->icon('tag'),
            ]),
            SidebarGroup::make('calendar', 'Calendar')->icon('calendar')->items([
                SidebarItem::make('Calendar', 'calendar-index')->icon('calendar'),
            ]),
        ]);
    }
}
