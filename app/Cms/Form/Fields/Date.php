<?php

namespace App\Cms\Form\Fields;

use App\Cms\Form\Concerns\HasLiveUpdates;
use App\Cms\Form\Field;

class Date extends Field
{
    use HasLiveUpdates;

    public string $type = 'date';
}
