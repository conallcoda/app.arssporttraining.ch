<?php

namespace App\Cms\Modules;

use App\Livewire\Training\PlanTemplateList;
use App\Livewire\Training\PlanTemplateView;
use Coda\Cms\ComponentDefinition;
use Coda\Cms\ComponentType;
use Coda\Cms\Module;
use Coda\Cms\PageDefinition;

class PlanTemplateModule extends Module
{
    public function name(): string
    {
        return 'plan-templates';
    }

    public function pages(): array
    {
        return [
            PageDefinition::make('plan-template-index')
                ->route('/plan-templates')
                ->title('ARS - Athlete Training // Plan Templates')
                ->heading('Plan Templates')
                ->content(['training.plan-template-list']),

            PageDefinition::make('plan-template-view')
                ->route('/plan-templates/{planTemplate}')
                ->title('ARS - Athlete Training // Plan Template')
                ->component(PlanTemplateView::class),
        ];
    }

    public function components(): array
    {
        return [
            ComponentDefinition::make('plan-template-list', PlanTemplateList::class)
                ->type(ComponentType::List),
        ];
    }
}
