<?php

namespace App\Data\Form\Fields;

use App\Data\Form\Concerns\HasOptions;
use App\Data\Form\Concerns\HasPlaceholder;
use App\Data\Form\Concerns\HasSelectVariants;
use App\Data\Form\Field;

class Pillbox extends Field
{
    use HasOptions;
    use HasPlaceholder;
    use HasSelectVariants;

    public string $type = 'pillbox';
}
