<?php

namespace App\Form\Fields;

use App\Form\Concerns\HasOptions;
use App\Form\Concerns\HasPlaceholder;
use App\Form\Concerns\HasSelectVariants;
use App\Form\Field;

class Pillbox extends Field
{
    use HasOptions;
    use HasPlaceholder;
    use HasSelectVariants;

    public string $type = 'pillbox';
}
