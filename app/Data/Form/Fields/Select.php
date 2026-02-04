<?php

namespace App\Data\Form\Fields;

use App\Data\Form\Concerns\HasLiveUpdates;
use App\Data\Form\Concerns\HasOptions;
use App\Data\Form\Concerns\HasPlaceholder;
use App\Data\Form\Concerns\HasSelectVariants;
use App\Data\Form\Concerns\HasUnique;
use App\Data\Form\Field;

class Select extends Field
{
    use HasLiveUpdates;
    use HasOptions;
    use HasPlaceholder;
    use HasSelectVariants;
    use HasUnique;

    public string $type = 'select';
}
