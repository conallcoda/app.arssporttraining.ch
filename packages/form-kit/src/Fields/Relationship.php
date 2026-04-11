<?php

namespace Coda\FormKit\Fields;

use Coda\FormKit\Concerns\HasOptions;
use Coda\FormKit\Concerns\HasPlaceholder;
use Coda\FormKit\Concerns\HasSortable;
use Coda\FormKit\Field;

class Relationship extends Field
{
    use HasOptions;
    use HasPlaceholder;
    use HasSortable;

    public string $type = 'relationship';
}
