<?php

namespace Coda\FormKit\Fields;

use Coda\FormKit\Concerns\HasPlaceholder;
use Coda\FormKit\Field;

class DocumentWriter extends Field
{
    use HasPlaceholder;

    public string $type = 'document-writer';

    public int $minHeight = 480;

    public bool $autofocus = false;

    /** @var array<int, string> */
    public array $chartTypes = ['bar', 'line', 'pie', 'doughnut'];

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->rules(['array']);
    }

    public function minHeight(int $minHeight): static
    {
        $this->minHeight = max(240, $minHeight);

        return $this;
    }

    public function autofocus(bool $autofocus = true): static
    {
        $this->autofocus = $autofocus;

        return $this;
    }

    /**
     * @param  array<int, string>  $chartTypes
     */
    public function chartTypes(array $chartTypes): static
    {
        $this->chartTypes = array_values(array_filter(
            $chartTypes,
            static fn (mixed $chartType): bool => is_string($chartType) && $chartType !== ''
        ));

        return $this;
    }
}
