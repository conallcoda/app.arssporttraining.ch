<?php

namespace App\Rules;

use App\Models\Metrics\MetricType;
use App\Models\Metrics\Contracts\HasMetricTypes;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidMetricType implements ValidationRule
{
    public function __construct(
        private string $modelBase,
        private ?string $modelSub = null
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            $fail("The {$attribute} is required.");
            return;
        }

        $resolvedClass = MetricType::resolveModelClass($this->modelBase);

        if (!$resolvedClass || !class_exists($resolvedClass)) {
            $fail("The model base {$this->modelBase} could not be resolved.");
            return;
        }

        if ($this->modelSub) {
            $instance = new $resolvedClass();
            if (method_exists($instance, 'getChildTypes')) {
                $childTypes = $instance->getChildTypes();
                if (isset($childTypes[$this->modelSub])) {
                    $resolvedClass = $childTypes[$this->modelSub];
                }
            }
        }

        if (!in_array(HasMetricTypes::class, class_implements($resolvedClass) ?: [])) {
            $fail("The model {$resolvedClass} must implement HasMetricTypes interface.");
            return;
        }

        $allowedTypes = $resolvedClass::getAllowedMetricTypes();

        if (!in_array($value, $allowedTypes, true)) {
            $fail("The {$attribute} must be one of: " . implode(', ', $allowedTypes));
        }
    }
}
