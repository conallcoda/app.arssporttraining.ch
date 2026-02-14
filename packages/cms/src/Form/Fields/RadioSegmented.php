<?php

namespace Coda\Cms\Form\Fields;

use Coda\Cms\Form\Concerns\HasLiveUpdates;
use Coda\Cms\Form\Concerns\HasOptions;
use Coda\Cms\Form\Field;

class RadioSegmented extends Field
{
    use HasLiveUpdates;
    use HasOptions;

    public string $type = 'radioSegmented';
}
