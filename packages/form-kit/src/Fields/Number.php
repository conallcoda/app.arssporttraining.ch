<?php

namespace Coda\FormKit\Fields;

use Coda\FormKit\Concerns\HasComputedFrom;
use Coda\FormKit\Concerns\HasLiveUpdates;
use Coda\FormKit\Concerns\HasNumericConstraints;
use Coda\FormKit\Concerns\HasPlaceholder;
use Coda\FormKit\Concerns\HasSuffix;
use Coda\FormKit\Field;

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
