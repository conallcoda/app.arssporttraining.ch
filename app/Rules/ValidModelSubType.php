<?php

namespace App\Rules;

use App\Models\Metrics\MetricType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidModelSubType implements ValidationRule
{
    public function __construct(private string $modelBase)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return; // Allow null/empty
        }

        $validSubTypes = MetricType::getChildTypesForModel($this->modelBase);

        if (empty($validSubTypes)) {
            $fail("The {$attribute} cannot be set because {$this->modelBase} has no child types.");
            return;
        }

        if (!in_array($value, $validSubTypes, true)) {
            $fail("The {$attribute} must be one of: " . implode(', ', $validSubTypes));
        }
    }
}
