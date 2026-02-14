<?php

namespace Coda\Cms\Form\Fields;

use Coda\Cms\Form\Concerns\HasLiveUpdates;
use Coda\Cms\Form\Concerns\HasPlaceholder;
use Coda\Cms\Form\Field;

class Url extends Field
{
    use HasLiveUpdates;
    use HasPlaceholder;

    public string $type = 'url';

    public ?string $validationRules = 'nullable|url:https';
}
