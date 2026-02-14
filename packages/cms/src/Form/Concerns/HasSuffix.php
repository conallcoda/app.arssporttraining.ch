<?php

namespace Coda\Cms\Form\Concerns;

trait HasSuffix
{
    public ?string $suffix = null;

    /** @var array<string, string>|null */
    public ?array $suffixMap = null;

    public function suffix(string $suffix): static
    {
        $this->suffix = $suffix;

        return $this;
    }

    /** @param array<string, string> $map */
    public function suffixMap(array $map): static
    {
        $this->suffixMap = $map;

        return $this;
    }

    public function resolveSuffix(array $siblingData = []): ?string
    {
        if ($this->suffixMap) {
            foreach ($siblingData as $value) {
                if (isset($this->suffixMap[$value])) {
                    return $this->suffixMap[$value];
                }
            }
        }

        return $this->suffix;
    }
}
