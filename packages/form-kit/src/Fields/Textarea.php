<?php

namespace Coda\FormKit\Fields;

use Coda\FormKit\Concerns\HasLiveUpdates;
use Coda\FormKit\Concerns\HasPlaceholder;
use Coda\FormKit\Field;

class Textarea extends Field
{
    use HasLiveUpdates;
    use HasPlaceholder;

    public string $type = 'textarea';

    public bool $autosize = true;

    public function autosize(bool $autosize = true): static
    {
        $this->autosize = $autosize;

        return $this;
    }
}
