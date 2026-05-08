<?php

namespace App\Livewire\Concerns;

use App\Data\Exercise\Preview\OverrideManager;
use App\Data\Exercise\Preview\PreviewGrid;
use Livewire\Attributes\Computed;

trait InteractsWithDisplayGridCopying
{
    #[Computed]
    public function copyBuckets(): array
    {
        $buckets = [];
        $displayGrid = $this->displayGridForCopy();
        $groupKind = strtolower((string) ($displayGrid->groupColumnLabel ?? 'group'));
        $sessionOnly = $this->displayGridUsesSessionOnlyCopy();

        foreach ($displayGrid->groups as $group) {
            $groupLabel = trim(strip_tags((string) ($group->label ?? '')));
            $groupExpanded = in_array($group->index, $this->expandedIndexesForCopy(), true) || (bool) ($group->expanded ?? false);
            $groupSessions = collect($group->sessions ?? [])
                ->map(fn ($session): array => [
                    'week' => (int) $session->weekIndex,
                    'session' => (int) $session->sessionIndex,
                    'locked' => (bool) ($session->locked ?? false),
                    'number' => (int) $session->sessionNumber,
                ])
                ->values()
                ->all();

            if ($sessionOnly || $groupExpanded || ! ($displayGrid->showGroupColumn ?? false)) {
                foreach ($groupSessions as $session) {
                    $key = 'session:'.$session['week'].':'.$session['session'];
                    $buckets[$key] = [
                        'key' => $key,
                        'type' => 'session',
                        'label' => 'Session '.$session['number'],
                        'sessions' => [$session],
                        'locked' => (bool) ($session['locked'] ?? false),
                    ];
                }

                continue;
            }

            $key = 'group:'.$group->index;
            $buckets[$key] = [
                'key' => $key,
                'type' => $groupKind,
                'label' => ucfirst($groupKind).' '.$groupLabel,
                'sessions' => $groupSessions,
                'locked' => collect($groupSessions)->contains(fn (array $session): bool => (bool) ($session['locked'] ?? false)),
            ];
        }

        return $buckets;
    }

    #[Computed]
    public function copyMenuOptions(): array
    {
        $buckets = $this->copyBuckets;
        $options = [];

        foreach ($buckets as $currentKey => $currentBucket) {
            $options[$currentKey] = [
                'from' => [],
                'to' => [],
            ];

            if (($currentBucket['locked'] ?? false) === true) {
                continue;
            }

            foreach ($buckets as $otherKey => $otherBucket) {
                if ($currentKey === $otherKey) {
                    continue;
                }

                if (($otherBucket['locked'] ?? false) === true) {
                    continue;
                }

                $options[$currentKey]['from'][] = [
                    'source' => $otherKey,
                    'target' => $currentKey,
                    'label' => $otherBucket['label'],
                ];

                $options[$currentKey]['to'][] = [
                    'source' => $currentKey,
                    'target' => $otherKey,
                    'label' => $otherBucket['label'],
                ];
            }
        }

        return $options;
    }

    public function copyDisplayBucket(string $sourceKey, string $targetKey): void
    {
        $buckets = $this->copyBuckets;
        $sourceBucket = $buckets[$sourceKey] ?? null;
        $targetBucket = $buckets[$targetKey] ?? null;

        if ($sourceBucket === null || $targetBucket === null || $sourceKey === $targetKey) {
            return;
        }

        if (($sourceBucket['locked'] ?? false) === true || ($targetBucket['locked'] ?? false) === true) {
            return;
        }

        if ($this->displayGridUsesSessionOnlyCopy() && (($sourceBucket['type'] ?? null) !== 'session' || ($targetBucket['type'] ?? null) !== 'session')) {
            return;
        }

        $sourceSession = $sourceBucket['sessions'][0] ?? null;

        if ($sourceSession === null) {
            return;
        }

        $gridOverrides = $this->currentGridOverridesForCopy();
        $previewGrid = $this->previewGridForCopy();
        $defaultsGrid = $this->defaultsGridForCopy();

        foreach ($targetBucket['sessions'] as $targetSession) {
            if (($targetSession['locked'] ?? false) === true) {
                continue;
            }

            $gridOverrides = OverrideManager::copySessionOverrides(
                $gridOverrides,
                $previewGrid,
                $defaultsGrid,
                (int) $sourceSession['week'],
                (int) $sourceSession['session'],
                (int) $targetSession['week'],
                (int) $targetSession['session'],
                sourceSetCount: $this->resolvedCopySessionSetCount($previewGrid, (int) $sourceSession['week'], (int) $sourceSession['session']),
                targetSetCount: $this->resolvedCopySessionSetCount($previewGrid, (int) $targetSession['week'], (int) $targetSession['session']),
                skipSessionFields: ['sets'],
            );
        }

        $this->persistGridOverridesFromCopy($gridOverrides);
    }

    public function resetDisplayBucket(string $bucketKey): void
    {
        $bucket = $this->copyBuckets[$bucketKey] ?? null;

        if ($bucket === null) {
            return;
        }

        $gridOverrides = $this->currentGridOverridesForCopy();

        foreach ($bucket['sessions'] as $session) {
            $week = (int) $session['week'];
            $sessionIndex = (int) $session['session'];

            $gridOverrides['sessions'] = collect($gridOverrides['sessions'] ?? [])
                ->reject(fn (array $override): bool => (int) ($override['week'] ?? -1) === $week && (int) ($override['session'] ?? -1) === $sessionIndex)
                ->values()
                ->all();

            $gridOverrides['cells'] = collect($gridOverrides['cells'] ?? [])
                ->reject(fn (array $override): bool => (int) ($override['week'] ?? -1) === $week && (int) ($override['session'] ?? -1) === $sessionIndex)
                ->values()
                ->all();
        }

        $this->persistGridOverridesFromCopy($gridOverrides);
    }

    protected function displayGridUsesSessionOnlyCopy(): bool
    {
        return method_exists($this, 'usesSessionOnlyDisplayCopy') && $this->usesSessionOnlyDisplayCopy();
    }

    protected function resolvedCopySessionSetCount(PreviewGrid $grid, int $week, int $session): int
    {
        $setsColumn = collect($grid->weekColumns)->firstWhere('field', 'sets');
        $value = $setsColumn?->getCellValue($week, 0, $session);

        if (is_numeric($value)) {
            return max(0, (int) $value);
        }

        return max(0, $grid->setCount);
    }

    abstract protected function displayGridForCopy(): PreviewGrid;

    abstract protected function previewGridForCopy(): PreviewGrid;

    abstract protected function defaultsGridForCopy(): PreviewGrid;

    /** @return int[] */
    abstract protected function expandedIndexesForCopy(): array;

    /** @return array{sessions: array, cells: array} */
    abstract protected function currentGridOverridesForCopy(): array;

    /** @param array{sessions: array, cells: array} $gridOverrides */
    abstract protected function persistGridOverridesFromCopy(array $gridOverrides): void;
}
