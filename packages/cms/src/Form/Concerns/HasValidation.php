<?php

namespace Coda\Cms\Form\Concerns;

trait HasValidation
{
    public bool $required = false;

    public bool $disabled = false;

    public ?string $validationRules = null;

    public function required(bool $required = true): static
    {
        $this->required = $required;

        return $this;
    }

    public function disabled(bool $disabled = true): static
    {
        $this->disabled = $disabled;

        return $this;
    }

    public function rules(string $rules): static
    {
        $this->validationRules = $rules;

        return $this;
    }
}
