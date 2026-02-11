<?php

namespace App\Cms\Form\Fields;

use App\Cms\Form\Concerns\HasComputedFrom;
use App\Cms\Form\Concerns\HasLiveUpdates;
use App\Cms\Form\Concerns\HasNumericConstraints;
use App\Cms\Form\Concerns\HasPlaceholder;
use App\Cms\Form\Concerns\HasSuffix;
use App\Cms\Form\Field;

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
