<?php

namespace Coda\FormKit\Fields;

use Coda\FormKit\Concerns\HasLiveUpdates;
use Coda\FormKit\Concerns\HasNumericConstraints;
use Coda\FormKit\Concerns\HasPlaceholder;
use Coda\FormKit\Field;

class Duration extends Field
{
    use HasLiveUpdates;
    use HasNumericConstraints;
    use HasPlaceholder;

    public string $type = 'duration';

    protected ?string $unitField = null;

    protected string $defaultUnit = 'seconds';

    protected ?string $customSuffix = null;

    /** @var array<string, mixed>|null */
    public ?array $defaultMap = null;

    protected array $suffixes = [
        'seconds' => 'sec',
        'minutes' => 'min',
        'mm:ss' => 'mm:ss',
    ];

    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->min = 0;
    }

    /** @param array<string, mixed> $map */
    public function defaultMap(array $map): static
    {
        $this->defaultMap = $map;

        return $this;
    }

    public function unit(string $fieldName): static
    {
        $this->unitField = $fieldName;

        return $this;
    }

    public function defaultUnit(string $unit): static
    {
        $this->defaultUnit = $unit;

        return $this;
    }

    public function suffix(string $suffix): static
    {
        $this->customSuffix = $suffix;

        return $this;
    }

    public function resolveUnit(array $siblingData = []): string
    {
        if ($this->unitField && isset($siblingData[$this->unitField])) {
            return $siblingData[$this->unitField];
        }

        return $this->defaultUnit;
    }

    public function isMasked(array $siblingData = []): bool
    {
        return $this->resolveUnit($siblingData) === 'mm:ss';
    }

    public function resolveMask(array $siblingData = []): ?string
    {
        return $this->isMasked($siblingData) ? '99:99' : null;
    }

    public function resolveDefault(array $siblingData = []): mixed
    {
        if ($this->defaultMap) {
            $unit = $this->resolveUnit($siblingData);

            if (isset($this->defaultMap[$unit])) {
                return $this->defaultMap[$unit];
            }
        }

        return $this->default;
    }

    public function resolveSuffix(array $siblingData = []): ?string
    {
        if ($this->customSuffix !== null) {
            return $this->customSuffix;
        }

        $unit = $this->resolveUnit($siblingData);

        return $this->suffixes[$unit] ?? null;
    }
}
