<?php

namespace App\Cms\Modules;

use App\Livewire\Database\AthleteDetailsPage;
use App\Livewire\Database\AthleteList;
use App\Livewire\Database\AthleteMetricIndex;
use App\Livewire\Database\AthleteMetricList;
use App\Models\Users\User;
use Coda\Cms\ComponentDefinition;
use Coda\Cms\ComponentType;
use Coda\Cms\Module;
use Coda\Cms\PageDefinition;

class AthleteModule extends Module
{
    public function name(): string
    {
        return 'athletes';
    }

    public function pages(): array
    {
        return [
            PageDefinition::make('athlete-index')
                ->route('/athletes')
                ->heading('Athletes')
                ->content(['database.athlete-list']),
            PageDefinition::make('athlete-metric-index')
                ->route('/athletes/{athleteId}/metrics')
                ->heading('Metrics')
                ->component(AthleteMetricIndex::class),
        ];
    }

    public function detailPages(): array
    {
        return [
            $this->detailsPage(
                name: 'athlete-details',
                route: '/athletes/{record}',
                listComponent: AthleteList::class,
                component: AthleteDetailsPage::class,
            )
                ->bindCrumb('record', User::class),
        ];
    }

    public function components(): array
    {
        return [
            ComponentDefinition::make('athlete-list', AthleteList::class)
                ->type(ComponentType::List),
            ComponentDefinition::make('athlete-metric-list', AthleteMetricList::class)
                ->type(ComponentType::List),
        ];
    }
}
