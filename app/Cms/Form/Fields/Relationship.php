<?php

namespace App\Cms\Form\Fields;

use App\Cms\Form\Concerns\HasOptions;
use App\Cms\Form\Concerns\HasPlaceholder;
use App\Cms\Form\Concerns\HasSortable;
use App\Cms\Form\Field;

class Relationship extends Field
{
    use HasOptions;
    use HasPlaceholder;
    use HasSortable;

    public string $type = 'relationship';
}
