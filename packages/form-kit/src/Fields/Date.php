<?php

namespace Coda\FormKit\Fields;

use Coda\FormKit\Concerns\HasLiveUpdates;
use Coda\FormKit\Field;

class Date extends Field
{
    use HasLiveUpdates;

    public string $type = 'date';

    public bool $selectableHeader = false;

    public bool|string $withInputs = false;

    public bool $clearable = false;

    public ?string $pickerType = null;

    public function selectableHeader(bool $selectableHeader = true): static
    {
        $this->selectableHeader = $selectableHeader;

        return $this;
    }

    public function withInputs(bool|string $withInputs = true): static
    {
        $this->withInputs = $withInputs;

        return $this;
    }

    public function clearable(bool $clearable = true): static
    {
        $this->clearable = $clearable;

        return $this;
    }

    public function pickerType(?string $pickerType): static
    {
        $this->pickerType = $pickerType;

        return $this;
    }
}
