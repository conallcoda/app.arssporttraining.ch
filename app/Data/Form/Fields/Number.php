<?php

namespace App\Data\Form\Fields;

use App\Data\Form\Concerns\HasComputedFrom;
use App\Data\Form\Concerns\HasLiveUpdates;
use App\Data\Form\Concerns\HasNumericConstraints;
use App\Data\Form\Concerns\HasPlaceholder;
use App\Data\Form\Concerns\HasSuffix;
use App\Data\Form\Field;

class Number extends Field
{
    use HasComputedFrom;
    use HasLiveUpdates;
    use HasNumericConstraints;
    use HasPlaceholder;
    use HasSuffix;

    public string $type = 'number';
}
