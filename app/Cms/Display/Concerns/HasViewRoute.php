<?php

namespace App\Cms\Display\Concerns;

trait HasViewRoute
{
    public ?string $viewClass = null;

    public function getViewRouteName(): ?string
    {
        if (! $this->viewClass) {
            return null;
        }

        $className = class_basename($this->viewClass);

        return str($className)->kebab()->toString();
    }
}
