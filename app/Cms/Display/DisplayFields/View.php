<?php

namespace App\Cms\Display\DisplayFields;

use App\Cms\Display\Concerns\HasPrefix;
use App\Cms\Display\Concerns\HasSuffix;
use App\Cms\Display\Concerns\HasViewRoute;
use App\Cms\Display\DisplayField;

class View extends DisplayField
{
    use HasPrefix;
    use HasSuffix;
    use HasViewRoute;

    public string $type = 'view';

    public function __construct(string $field, string $viewClass)
    {
        parent::__construct($field);

        $this->viewClass = $viewClass;
    }

    public static function make(string $field, string $viewClass = ''): static
    {
        return new static($field, $viewClass);
    }
}
