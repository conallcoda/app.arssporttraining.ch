<?php

namespace App\Form\Fields;

use App\Form\Concerns\HasLiveUpdates;
use App\Form\Concerns\HasOptions;
use App\Form\Field;

class RadioSegmented extends Field
{
    use HasLiveUpdates;
    use HasOptions;

    public string $type = 'radioSegmented';
}
