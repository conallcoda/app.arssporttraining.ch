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
            $groupExpanded = (bool) ($group->expanded ?? false);
            $groupSessions = collect($group->sessions ?? [])
                ->map(function ($session): array {
                    $week = (int) $session->weekIndex;
                    $sessionIndex = (int) $session->sessionIndex;

                    return [
                        'week' => $week,
                        'session' => $sessionIndex,
                        'locked' => $this->displayBucketSessionLocked($week, $sessionIndex, (bool) ($session->locked ?? false)),
                        'number' => (int) $session->sessionNumber,
                    ];
                })
                ->values()
                ->all();

            if ($sessionOnly || $groupExpanded) {
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

            if (! empty($options[$currentKey]['to'])) {
                $options[$currentKey]['toAll'] = [
                    'source' => $currentKey,
                    'label' => __('All'),
                ];
            }
        }

        return $options;
    }

    #[Computed]
    public function previewMenuOptions(): array
    {
        $options = [];

        foreach ($this->copyBuckets as $key => $bucket) {
            $options[$key] = collect($bucket['sessions'] ?? [])
                ->map(fn (array $session): array => [
                    'week' => (int) $session['week'],
                    'session' => (int) $session['session'],
                    'number' => (int) ($session['number'] ?? 0),
                    'label' => __('Session :number', ['number' => (int) ($session['number'] ?? 0)]),
                ])
                ->values()
                ->all();
        }

        return $options;
    }

    #[Computed]
    public function resetMenuOptions(): array
    {
        return collect($this->copyBuckets)
            ->mapWithKeys(fn (array $bucket, string $key): array => [
                $key => ! (bool) ($bucket['locked'] ?? false),
            ])
            ->all();
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

        $gridOverrides = $this->applyDisplayBucketCopy(
            $gridOverrides,
            $sourceSession,
            $targetBucket,
            $previewGrid,
            $defaultsGrid,
        );

        $this->persistGridOverridesFromCopy($gridOverrides);
    }

    public function copyDisplayBucketToAll(string $sourceKey): void
    {
        $buckets = $this->copyBuckets;
        $sourceBucket = $buckets[$sourceKey] ?? null;

        if ($sourceBucket === null || ($sourceBucket['locked'] ?? false) === true) {
            return;
        }

        if ($this->displayGridUsesSessionOnlyCopy() && (($sourceBucket['type'] ?? null) !== 'session')) {
            return;
        }

        $sourceSession = $sourceBucket['sessions'][0] ?? null;

        if ($sourceSession === null) {
            return;
        }

        $gridOverrides = $this->currentGridOverridesForCopy();
        $previewGrid = $this->previewGridForCopy();
        $defaultsGrid = $this->defaultsGridForCopy();
        $targets = $this->copyMenuOptions[$sourceKey]['to'] ?? [];

        foreach ($targets as $option) {
            $targetBucket = $buckets[$option['target']] ?? null;

            if ($targetBucket === null) {
                continue;
            }

            $gridOverrides = $this->applyDisplayBucketCopy(
                $gridOverrides,
                $sourceSession,
                $targetBucket,
                $previewGrid,
                $defaultsGrid,
            );
        }

        $this->persistGridOverridesFromCopy($gridOverrides);
    }

    public function resetDisplayBucket(string $bucketKey): void
    {
        $bucket = $this->copyBuckets[$bucketKey] ?? null;

        if ($bucket === null || (bool) ($bucket['locked'] ?? false)) {
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

    protected function displayBucketSessionLocked(int $week, int $session, bool $fallback = false): bool
    {
        if (method_exists($this, 'isSessionResetLocked')) {
            return (bool) $this->isSessionResetLocked($week, $session) || $fallback;
        }

        return $fallback;
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

    /**
     * @param  array{sessions: array, cells: array}  $gridOverrides
     * @param  array{week: int, session: int, locked?: bool, number?: int}  $sourceSession
     * @param  array{sessions: array, locked?: bool}  $targetBucket
     * @return array{sessions: array, cells: array}
     */
    protected function applyDisplayBucketCopy(
        array $gridOverrides,
        array $sourceSession,
        array $targetBucket,
        PreviewGrid $previewGrid,
        PreviewGrid $defaultsGrid,
    ): array {
        if (($targetBucket['locked'] ?? false) === true) {
            return $gridOverrides;
        }

        foreach ($targetBucket['sessions'] as $targetSession) {
            if (($targetSession['locked'] ?? false) === true) {
                continue;
            }

            $sourceSetCount = $this->resolvedCopySessionSetCount($previewGrid, (int) $sourceSession['week'], (int) $sourceSession['session']);

            $gridOverrides = OverrideManager::copySessionOverrides(
                $gridOverrides,
                $previewGrid,
                $defaultsGrid,
                (int) $sourceSession['week'],
                (int) $sourceSession['session'],
                (int) $targetSession['week'],
                (int) $targetSession['session'],
                sourceSetCount: $sourceSetCount,
                targetSetCount: $sourceSetCount,
            );
        }

        return $gridOverrides;
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
