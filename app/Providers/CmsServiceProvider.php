<?php

namespace App\Providers;

use App\Cms\Modules\AthleteGroupModule;
use App\Cms\Modules\AthleteModule;
use App\Cms\Modules\CategoryModule;
use App\Cms\Modules\EquipmentModule;
use App\Cms\Modules\ExerciseExternalModule;
use App\Cms\Modules\ExerciseModule;
use App\Cms\Modules\ExerciseTemplateModule;
use App\Cms\Modules\ModifiersModule;
use App\Cms\Modules\PlanTemplateModule;
use App\Cms\Modules\ProgramCategoryModule;
use App\Cms\Modules\TrainingPlanModule;
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
        $registry->register(new TrainingPlanModule);
        $registry->register(new PlanTemplateModule);
        $registry->register(new ProgramCategoryModule);

        $registry->setNavigation([
            SidebarGroup::make('database', 'Database')->icon('database')->items([
                SidebarItem::make('Athletes', 'athlete-index')->icon('users')->tabs([
                    Tab::make('Athletes', 'athlete-index'),
                    Tab::make('Groups', 'athlete-group-index'),
                ]),
                SidebarItem::make('Exercises', 'exercise-index')->icon('dumbbell')->tabs([
                    Tab::make('Exercises', 'exercise-index'),
                    Tab::make('Templates', 'exercise-template-index'),
                    Tab::make('Categories', 'category-index'),
                    Tab::make('Equipment', 'equipment-index'),
                    Tab::make('Modifiers', 'modifiers-index'),
                    Tab::make('Import', 'exercise-external-index'),
                ]),
            ]),
            SidebarGroup::make('training', 'Training')->icon('trophy')->items([
                SidebarItem::make('Plans', 'training-plan-index')->icon('clipboard-list')->tabs([
                    Tab::make('Training Plans', 'training-plan-index'),
                    Tab::make('Templates', 'plan-template-index'),
                ]),
                SidebarItem::make('Settings', 'program-category-index')->icon('settings')->tabs([
                    Tab::make('Program Categories', 'program-category-index'),
                ]),
            ]),
        ]);
    }
}
