<?php

namespace Coda\Cms\Form\Fields;

use Coda\Cms\Form\Concerns\HasLiveUpdates;
use Coda\Cms\Form\Field;

class Preset extends Field
{
    use HasLiveUpdates;

    public string $type = 'preset';

    public array $presets = [];

    public ?Field $otherField = null;

    public array $otherFields = [];

    public function presets(array $presets): static
    {
        $this->presets = $presets;

        return $this;
    }

    public function otherField(Field $field): static
    {
        $this->otherField = $field;

        return $this;
    }

    public function otherFields(array $fields): static
    {
        $this->otherFields = $fields;

        return $this;
    }

    public function isMapped(): bool
    {
        return ! empty($this->presets) && isset($this->presets[0]['values']);
    }

    public function hasOther(): bool
    {
        return $this->otherField !== null || ! empty($this->otherFields);
    }
}
