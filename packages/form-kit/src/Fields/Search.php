<?php

namespace Coda\FormKit\Fields;

use Coda\FormKit\Concerns\HasLiveUpdates;
use Coda\FormKit\Concerns\HasPlaceholder;
use Coda\FormKit\Field;

class Search extends Field
{
    use HasLiveUpdates;
    use HasPlaceholder;

    public string $type = 'search';

    public string $size = 'sm';

    public string $icon = 'search';

    public static function make(string $name): static
    {
        return parent::make($name)->updateOn('live', 300);
    }

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
