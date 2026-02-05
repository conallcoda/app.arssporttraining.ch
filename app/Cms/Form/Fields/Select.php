<?php

namespace App\Cms\Form\Fields;

use App\Cms\Form\Concerns\HasLiveUpdates;
use App\Cms\Form\Concerns\HasOptions;
use App\Cms\Form\Concerns\HasPlaceholder;
use App\Cms\Form\Concerns\HasSelectVariants;
use App\Cms\Form\Concerns\HasUnique;
use App\Cms\Form\Field;

class Select extends Field
{
    use HasLiveUpdates;
    use HasOptions;
    use HasPlaceholder;
    use HasSelectVariants;
    use HasUnique;

    public string $type = 'select';
}
