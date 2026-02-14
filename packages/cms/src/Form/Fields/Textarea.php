<?php

namespace Coda\Cms\Form\Fields;

use Coda\Cms\Form\Concerns\HasLiveUpdates;
use Coda\Cms\Form\Concerns\HasPlaceholder;
use Coda\Cms\Form\Field;

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
