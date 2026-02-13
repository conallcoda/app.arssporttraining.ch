<?php

namespace App\Cms\Form\Fields;

use App\Cms\Form\Concerns\HasLiveUpdates;
use App\Cms\Form\Concerns\HasPlaceholder;
use App\Cms\Form\Field;

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
