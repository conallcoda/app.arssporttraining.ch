<?php

namespace Coda\FormKit\Fields;

use Coda\FormKit\Concerns\HasPlaceholder;
use Coda\FormKit\Field;

class Search extends Field
{
    use HasPlaceholder;

    public string $type = 'search';

    public string $size = 'sm';

    public string $icon = 'search';

    public function size(string $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function icon(string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }
}
