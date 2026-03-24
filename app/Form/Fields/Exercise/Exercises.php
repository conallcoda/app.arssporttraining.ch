<?php

namespace App\Form\Fields\Exercise;

use App\Models\Exercise\Exercise;
use Coda\Cms\Form\Fields\Relationship;

class Exercises extends Relationship
{
    public bool $groupable = false;

    /** @var array<string, string> */
    public array $groupOptions = [];

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Exercises';
        $this->placeholder = 'Select exercise';
        $this->sortable = true;
        $this->default = [];
    }

    public function groupable(): static
    {
        $this->groupable = true;
        $this->groupOptions = array_combine(range('A', 'Z'), range('A', 'Z'));

        return $this;
    }

    public function withOptions(): static
    {
        $this->optionLoader = fn () => Exercise::query()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn ($exercise) => [$exercise->id => $exercise->name])
            ->all();

        return $this;
    }
}
