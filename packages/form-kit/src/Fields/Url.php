<?php

namespace Coda\FormKit\Fields;

use Coda\FormKit\Concerns\HasLiveUpdates;
use Coda\FormKit\Concerns\HasPlaceholder;
use Coda\FormKit\Field;

class Url extends Field
{
    use HasLiveUpdates;
    use HasPlaceholder;

    public string $type = 'url';

    public string|array|\Closure|null $validationRules = 'nullable|url:https';
}
