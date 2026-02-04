<?php

namespace App\Form\Fields;

use App\Form\Concerns\HasLiveUpdates;
use App\Form\Field;

class Date extends Field
{
    use HasLiveUpdates;

    public string $type = 'date';
}
