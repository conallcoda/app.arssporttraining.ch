<?php

namespace App\Form\Fields\Training\Program;

use App\Models\ProgramCategory as ProgramCategoryModel;
use Coda\Cms\Form\Fields\Select;

class ProgramCategory extends Select
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Category';
        $this->placeholder = 'Select a category';
        $this->required = true;
        $this->validationRules = 'required|integer|exists:program_categories,id';
        $this->default = null;
        $this->variant = 'listbox';
    }

    public function withOptions(): static
    {
        $this->optionLoader = fn () => ProgramCategoryModel::query()
            ->orderBy('sort')
            ->pluck('name', 'id')
            ->all();

        return $this;
    }
}
