<?php

namespace App\Filament\Extensions\Actions;

use Filament\Actions\DeleteAction as BaseDeleteAction;
use Illuminate\Database\Eloquent\Model;
use Closure;
use Filament\Actions\Action;
use LogicException;

class DeleteAction extends BaseDeleteAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $that = $this;
        $this->hidden(static function (?Model $record) use ($that): bool {

            if (is_null($record)) {
                return false;
            }
            if (! method_exists($record, 'trashed')) {
                return false;
            }

            return $record->trashed();
        });
    }

    public function getRecord(bool $withDefault = true): Model | array | null
    {
        $record = $this->evaluate($this->record);

        $isRecordKey = filled($record) && (! $record instanceof Model) && (! is_array($record));

        if ($isRecordKey && (! $this->resolveRecordUsing)) {
            throw new LogicException("Could not resolve record from key [{$record}] without a [resolveRecordUsing()] callback.");
        }

        if ($isRecordKey) {
            $record = $this->evaluate($this->resolveRecordUsing, [
                'key' => $record,
            ]);
        }

        if ($isRecordKey && $record && (! $this->record instanceof Closure)) {
            $this->record = $record;
        }

        if ($record) {
            return $record;
        }

        if ($record = $this->getGroup()?->getRecord($withDefault)) {
            return $record;
        }

        if (($this instanceof Action) && $record = $this->getSchemaContainer()?->getRecord()) {
            return $record;
        }

        if (($this instanceof Action) && $record = $this->getSchemaComponent()?->getRecord()) {
            return $record;
        }



        return ($withDefault && ($this instanceof Action)) ? $this->getHasActionsLivewire()?->getDefaultActionRecord($this) : null;
    }
}
