<?php

namespace Coda\FormKit\Fields;

use Coda\FormKit\Concerns\HasLiveUpdates;
use Coda\FormKit\Concerns\HasOptions;
use Coda\FormKit\Field;

class RadioSegmented extends Field
{
    use HasLiveUpdates;
    use HasOptions;

    public string $type = 'radioSegmented';
}
