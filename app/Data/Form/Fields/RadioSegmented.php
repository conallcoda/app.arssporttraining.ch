<?php

namespace App\Data\Form\Fields;

use App\Data\Form\Concerns\HasLiveUpdates;
use App\Data\Form\Concerns\HasOptions;
use App\Data\Form\Field;

class RadioSegmented extends Field
{
    use HasLiveUpdates;
    use HasOptions;

    public string $type = 'radioSegmented';
}
