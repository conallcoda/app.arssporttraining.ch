<?php

namespace App\Form\Fields;

use App\Form\Concerns\HasOptions;
use App\Form\Concerns\HasPlaceholder;
use App\Form\Concerns\HasSortable;
use App\Form\Field;

class Relationship extends Field
{
    use HasOptions;
    use HasPlaceholder;
    use HasSortable;

    public string $type = 'relationship';
}
