<?php

namespace Coda\Cms\Form\Fields;

use Coda\Cms\Form\Concerns\HasLiveUpdates;
use Coda\Cms\Form\Field;

class Date extends Field
{
    use HasLiveUpdates;

    public string $type = 'date';
}
