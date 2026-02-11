<?php

namespace App\Cms\Display\DisplayFields;

use App\Cms\Display\Concerns\HasPrefix;
use App\Cms\Display\Concerns\HasSuffix;
use App\Cms\Display\DisplayField;

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
