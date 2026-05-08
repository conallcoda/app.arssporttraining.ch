<?php

namespace Coda\Cms\Display\DisplayFields;

use Coda\Cms\Display\Concerns\HasModal;
use Coda\Cms\Display\DisplayField;

class TextWithBadgeGroups extends DisplayField
{
    use HasModal;

    public string $type = 'text-with-badge-groups';

    /** @var list<array{name: string, source: \Closure, color: ?string}> */
    public array $badgeGroups = [];

    public function badgeGroup(string $name, \Closure $source, ?string $color = null): static
    {
        $this->badgeGroups[] = [
            'name' => $name,
            'source' => $source,
            'color' => $color,
        ];

        return $this;
    }

    /** @return list<array{name: string, badges: list<array{label: string}>, color: ?string}> */
    public function getBadgeGroupData(mixed $item): array
    {
        return collect($this->badgeGroups)
            ->map(fn (array $group) => [
                'name' => $group['name'],
                'badges' => ($group['source'])($item),
                'color' => $group['color'],
            ])
            ->all();
    }
}
