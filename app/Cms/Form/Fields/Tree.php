<?php

namespace App\Cms\Form\Fields;

use App\Cms\Form\Concerns\HasPlaceholder;
use App\Cms\Form\Field;

class Tree extends Field
{
    use HasPlaceholder;

    public string $type = 'tree';

    /** @var list<array{value: int, name: string, children: list<mixed>}> */
    public array $treeOptions = [];

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->default = null;
    }

    public function options(array $treeOptions): static
    {
        $this->treeOptions = $treeOptions;

        return $this;
    }
}
