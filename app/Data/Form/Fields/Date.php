<?php

namespace App\Data\Form\Fields;

use App\Data\Form\Concerns\HasLiveUpdates;
use App\Data\Form\Field;

class Date extends Field
{
    use HasLiveUpdates;

    public string $type = 'date';
}
