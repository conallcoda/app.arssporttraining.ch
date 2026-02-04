<?php

namespace App\Form\Fields;

use App\Form\Concerns\HasLiveUpdates;
use App\Form\Concerns\HasOptions;
use App\Form\Concerns\HasPlaceholder;
use App\Form\Concerns\HasSelectVariants;
use App\Form\Concerns\HasUnique;
use App\Form\Field;

class Select extends Field
{
    use HasLiveUpdates;
    use HasOptions;
    use HasPlaceholder;
    use HasSelectVariants;
    use HasUnique;

    public string $type = 'select';
}
