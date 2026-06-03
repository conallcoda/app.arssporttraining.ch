<?php

namespace Coda\Cms\Display\DisplayFields;

use Coda\Cms\Display\DisplayField;

class Image extends DisplayField
{
    public string $type = 'image';

    public string $aspect = 'square';

    public string $fit = 'cover';

    public function aspect(string $aspect): static
    {
        $this->aspect = $aspect;

        return $this;
    }

    public function fit(string $fit): static
    {
        $this->fit = $fit;

        return $this;
    }
}
