<?php

namespace App\View\Components;

use App\Data\Form\Field;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class FormField extends Component
{
    public string $wireModel;

    public function __construct(
        public Field $field,
        public ?string $prefix = null,
        public ?array $repeaterItems = null,
        public ?int $currentIndex = null,
    ) {
        $this->wireModel = $prefix ? "{$prefix}.{$field->name}" : $field->name;
    }

    public function render(): View|Closure|string
    {
        return view('components.form-field');
    }
}
