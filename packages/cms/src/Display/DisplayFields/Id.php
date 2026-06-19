<?php

namespace Coda\Cms\Display\DisplayFields;

use Coda\Cms\Display\Concerns\HasPrefix;
use Coda\Cms\Display\Concerns\HasSuffix;
use Coda\Cms\Display\DisplayField;

class Id extends DisplayField
{
    use HasPrefix;
    use HasSuffix;

    public string $type = 'text';

    public function __construct()
    {
        parent::__construct('id');

        $this->label = 'ID';
        $this->width = 'w-16';
        $this->prefix = '#';
        $this->sticky = true;
    }

    public static function make(string $field = 'id'): static
    {
        return new static;
    }
}
