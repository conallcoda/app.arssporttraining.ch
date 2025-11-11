<?php

namespace App\Filament\Actions;

use Filament\Actions\CreateAction as BaseCreateAction;

class CreateAction extends BaseCreateAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Create');
    }
}
