<?php

namespace Coda\SchemaKit\Attributes;

interface ProvidesValidationRules
{
    /**
     * @return array<int, string|object>
     */
    public function rules(): array;
}
