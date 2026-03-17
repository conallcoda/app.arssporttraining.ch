<?php

namespace App\Form\Fields;

use Coda\Cms\Form\Fields\Select;

abstract class SelectEntity extends Select
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->searchable = true;
        $this->clearable = true;
    }

    public function withExtraOption(string $label, mixed $value = 0): static
    {
        $originalLoader = $this->optionLoader;

        $this->optionLoader = function () use ($originalLoader, $label, $value) {
            return [$value => $label] + ($originalLoader ? ($originalLoader)() : []);
        };

        return $this;
    }
}
