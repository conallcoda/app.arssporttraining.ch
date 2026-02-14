<?php

namespace Coda\Cms\Form\Fields;

use Coda\Cms\Form\Concerns\HasComputedFrom;
use Coda\Cms\Form\Concerns\HasLiveUpdates;
use Coda\Cms\Form\Concerns\HasNumericConstraints;
use Coda\Cms\Form\Concerns\HasPlaceholder;
use Coda\Cms\Form\Concerns\HasSuffix;
use Coda\Cms\Form\Field;

class Number extends Field
{
    use HasComputedFrom;
    use HasLiveUpdates;
    use HasNumericConstraints;
    use HasPlaceholder;
    use HasSuffix;

    public string $type = 'number';

    /** @var array<string, mixed>|null */
    public ?array $defaultMap = null;

    /** @param array<string, mixed> $map */
    public function defaultMap(array $map): static
    {
        $this->defaultMap = $map;

        return $this;
    }

    public function resolveDefault(array $siblingData = []): mixed
    {
        if ($this->defaultMap) {
            foreach ($siblingData as $value) {
                if (isset($this->defaultMap[$value])) {
                    return $this->defaultMap[$value];
                }
            }
        }

        return $this->default;
    }
}
