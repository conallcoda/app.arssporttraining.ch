<?php

namespace App\Form\Fields\Training\Program;

use App\Models\Tag;
use Coda\Cms\Form\Fields\Select;

class ExerciseCategory extends Select
{
    public array $colorMap = [];

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Category';
        $this->placeholder = 'Select a category';
        $this->required = true;
        $this->validationRules = 'required|integer|exists:tags,id';
        $this->default = null;
        $this->variant = 'listbox';
        $this->optionView = 'form.options.program-category';
    }

    public function withOptions(): static
    {
        $this->optionLoader = function () {
            $tags = Tag::query()
                ->forScope('exercise_category')
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->get(['id', 'name', 'color']);

            $this->colorMap = $tags->pluck('color', 'id')->all();

            return $tags->pluck('name', 'id')->all();
        };

        return $this;
    }
}
