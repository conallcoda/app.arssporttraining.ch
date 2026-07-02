<?php

namespace Coda\Cms\Display\DisplayFields;

use Coda\Cms\Display\Concerns\HasPrefix;
use Coda\Cms\Display\Concerns\HasSuffix;
use Coda\Cms\Display\Concerns\HasViewRoute;
use Coda\Cms\Display\DisplayField;

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
