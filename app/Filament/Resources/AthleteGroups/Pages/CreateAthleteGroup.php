<?php

namespace App\Filament\Resources\AthleteGroups\Pages;

use App\Filament\Pages\AbstractCreateRecord;
use App\Filament\Resources\AthleteGroups\AthleteGroupResource;
use App\Models\Users\Groups\AthleteGroup;

class CreateAthleteGroup extends AbstractCreateRecord
{
    protected static string $resource = AthleteGroupResource::class;

    protected function handleRecordCreation(array $data): AthleteGroup
    {
        $data['type'] = 'athlete';
        return AthleteGroup::create($data);
    }
}
