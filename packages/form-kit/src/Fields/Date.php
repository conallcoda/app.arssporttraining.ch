<?php

namespace Coda\FormKit\Fields;

use Coda\FormKit\Concerns\HasLiveUpdates;
use Coda\FormKit\Field;

class Date extends Field
{
    use HasLiveUpdates;

    public string $type = 'date';
}
