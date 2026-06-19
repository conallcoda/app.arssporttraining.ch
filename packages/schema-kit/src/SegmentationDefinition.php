<?php

namespace Coda\SchemaKit;

use InvalidArgumentException;

final class SegmentationDefinition
{
    private string $defaultGroupSlug = 'custom';

    private string $defaultGroupLabel = 'Custom Segments';

    /** @var array<string, SegmentGroupDefinition> */
    private array $groups = [];

    /** @var array<string, SegmentDefinition> */
    private array $segments = [];

    public static function make(): static
    {
        return new static;
    }

    public function defaultGroup(string $slug, ?string $label = null): static
    {
        $this->defaultGroupSlug = $slug;

        if ($label !== null) {
            $this->defaultGroupLabel = $label;
        }

        return $this;
    }

    public function group(SegmentGroupDefinition|string $group, ?callable $configure = null): SegmentGroupDefinition|static
    {
        if ($group instanceof SegmentGroupDefinition) {
            $this->groups[$group->slug()] = $group;

            return $this;
        }

        $definition = $this->groups[$group] ??= SegmentGroupDefinition::make($group);

        if ($configure === null) {
            return $definition;
        }

        $configure($definition);

        return $this;
    }

    public function segment(SegmentDefinition|string $segment, ?callable $configure = null): SegmentDefinition|static
    {
        if ($segment instanceof SegmentDefinition) {
            if ($segment->getGroup() === null) {
                $segment->group($this->defaultGroupSlug());
            }

            $this->segments[$segment->slug()] = $segment;

            return $this;
        }

        $definition = $this->segments[$segment] ??= SegmentDefinition::make($segment)->group($this->defaultGroupSlug());

        if ($configure === null) {
            return $definition;
        }

        $configure($definition);

        if ($definition->getGroup() === null) {
            $definition->group($this->defaultGroupSlug());
        }

        return $this;
    }

    public function defaultGroupSlug(): string
    {
        return $this->defaultGroupSlug;
    }

    /**
     * @return array<string, SegmentGroupDefinition>
     */
    public function getGroups(): array
    {
        $groups = $this->groups;

        $groups[$this->defaultGroupSlug] ??= SegmentGroupDefinition::make($this->defaultGroupSlug)
            ->label($this->defaultGroupLabel);

        return $groups;
    }

    /**
     * @return array<string, SegmentDefinition>
     */
    public function getSegments(): array
    {
        return $this->segments;
    }

    public function requireGroup(string $slug): SegmentGroupDefinition
    {
        return $this->getGroups()[$slug]
            ?? throw new InvalidArgumentException("Segment group [{$slug}] is not defined.");
    }

    public function requireSegment(string $slug): SegmentDefinition
    {
        return $this->segments[$slug]
            ?? throw new InvalidArgumentException("Segment [{$slug}] is not defined.");
    }
}
