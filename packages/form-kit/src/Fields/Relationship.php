<?php

namespace Coda\FormKit\Fields;

use Closure;
use Coda\FormKit\Concerns\HasCreateOption;
use Coda\FormKit\Concerns\HasOptions;
use Coda\FormKit\Concerns\HasPlaceholder;
use Coda\FormKit\Concerns\HasSortable;
use Coda\FormKit\Field;

class Relationship extends Field
{
    use HasOptions;
    use HasCreateOption;
    use HasPlaceholder;
    use HasSortable;

    public string $type = 'relationship';

    public ?string $optionView = null;

    public ?Closure $searchCallback = null;

    public function optionView(string $view): static
    {
        $this->optionView = $view;

        return $this;
    }

    public function searchable(Closure $callback): static
    {
        $this->searchCallback = $callback;

        return $this;
    }

    /**
     * @param  array<int|string>  $excludedIds
     * @return iterable<mixed>
     */
    public function getSearchResults(?string $query, mixed $currentValue = null, array $excludedIds = []): iterable
    {
        if ($this->searchCallback === null) {
            return [];
        }

        return ($this->searchCallback)((string) ($query ?? ''), $currentValue, $excludedIds);
    }
}
