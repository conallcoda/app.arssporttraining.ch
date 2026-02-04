<?php

namespace App\Data\Form\Fields;

use App\Data\Form\Concerns\HasOptions;
use App\Data\Form\Concerns\HasPlaceholder;
use App\Data\Form\Concerns\HasSortable;
use App\Data\Form\Field;

class Relationship extends Field
{
    use HasOptions;
    use HasPlaceholder;
    use HasSortable;

    public string $type = 'relationship';
}
