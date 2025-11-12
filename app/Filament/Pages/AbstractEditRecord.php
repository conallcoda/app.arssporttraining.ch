<?php


namespace App\Filament\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

abstract class AbstractEditRecord extends EditRecord
{

    protected function getHeaderActions(): array
    {
        return [
            $this->getCancelFormAction()->formId('form'),
            $this->getSaveFormAction()->formId('form'),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            DeleteAction::make()
                ->extraAttributes(['class' => 'ml-auto']),
            ForceDeleteAction::make(),
            RestoreAction::make(),
            $this->getCancelFormAction()->formId('form'),
            $this->getSaveFormAction()->formId('form'),
        ];
    }
}
