<?php

namespace App\Cms\Form\Fields;

use App\Cms\Form\Concerns\HasLiveUpdates;
use App\Cms\Form\Concerns\HasOptions;
use App\Cms\Form\Field;

class RadioSegmented extends Field
{
    use HasLiveUpdates;
    use HasOptions;

    public string $type = 'radioSegmented';
}
