<?php

namespace Coda\Cms\Livewire\Concerns;

use Illuminate\Support\Str;

trait WithUrlPrefix
{
    public bool $prefixUrl = false;

    protected function urlPrefix(): string
    {
        return Str::snake(class_basename(static::class)).'_';
    }

    protected function queryStringWithUrlPrefix(): array
    {
        $result = [];

        foreach ($this->urlProperties() as $property => $options) {
            if ($this->prefixUrl) {
                $options['as'] = $this->urlPrefix().($options['as'] ?? $property);
            }

            $result[$property] = $options;
        }

        return $result;
    }

    protected function urlProperties(): array
    {
        return [];
    }

    protected function prefixedPageName(): string
    {
        if ($this->prefixUrl) {
            return $this->urlPrefix().'page';
        }

        return 'page';
    }
}
