<?php

namespace App\Cms\Form\Fields;

use App\Cms\Form\Concerns\HasLiveUpdates;
use App\Cms\Form\Concerns\HasPlaceholder;
use App\Cms\Form\Field;

class Url extends Field
{
    use HasLiveUpdates;
    use HasPlaceholder;

    public string $type = 'url';

    public ?string $validationRules = 'nullable|url:https';
}
