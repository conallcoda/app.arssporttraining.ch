<?php

namespace App\Training\Handlers;

use App\Models\Exercise\Exercise;
use App\Training\Data\GridDefinition;
use App\Training\Data\TrainingBlock;

interface ExercisePlanHandlerInterface
{
    public function needsMeasuredData(): bool;

    public function hasSets(): bool;

    public function getGridDefinition(Exercise $exercise): GridDefinition;

    /** @return array<string, mixed> */
    public function resolveConfig(
        Exercise $exercise,
        array $pivotConfig,
        array $defaultOverrides,
        array $userOverrides,
        bool $isDefaultUser,
    ): array;

    /** @return array<int, array{field: string, label: string, hasOverride: bool}> */
    public function getConfigBadges(array $config): array;

    /** @return array<int, array{field: string, label: string, type: string, suffix?: string, min?: int, max?: int, step?: float, description?: string}> */
    public function getSettingsFields(Exercise $exercise): array;

    public function generateBlock(
        array $config,
        int $weeks,
        int $sessionsPerWeek,
        ?array $measuredData = null,
    ): ?TrainingBlock;

    /** @return array<string, mixed>|null */
    public function getHeaderInfo(?TrainingBlock $block, array $config): ?array;

    /** @return array<string, mixed> */
    public function getDefaultWeekValues(array $config): array;
}
