<?php

namespace App\Cms\Form\Fields;

use App\Cms\Form\Concerns\HasOptions;
use App\Cms\Form\Concerns\HasPlaceholder;
use App\Cms\Form\Concerns\HasSelectVariants;
use App\Cms\Form\Field;

class Pillbox extends Field
{
    use HasOptions;
    use HasPlaceholder;
    use HasSelectVariants;

    public string $type = 'pillbox';
}
