<?php

namespace App\Form\Fields;

use App\Form\Concerns\HasComputedFrom;
use App\Form\Concerns\HasLiveUpdates;
use App\Form\Concerns\HasNumericConstraints;
use App\Form\Concerns\HasPlaceholder;
use App\Form\Concerns\HasSuffix;
use App\Form\Field;

class Number extends Field
{
    use HasComputedFrom;
    use HasLiveUpdates;
    use HasNumericConstraints;
    use HasPlaceholder;
    use HasSuffix;

    public string $type = 'number';
}
