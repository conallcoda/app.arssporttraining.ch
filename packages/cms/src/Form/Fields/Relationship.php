<?php

namespace Coda\Cms\Form\Fields;

use Coda\Cms\Form\Concerns\HasOptions;
use Coda\Cms\Form\Concerns\HasPlaceholder;
use Coda\Cms\Form\Concerns\HasSortable;
use Coda\Cms\Form\Field;

class Relationship extends Field
{
    use HasOptions;
    use HasPlaceholder;
    use HasSortable;

    public string $type = 'relationship';
}
