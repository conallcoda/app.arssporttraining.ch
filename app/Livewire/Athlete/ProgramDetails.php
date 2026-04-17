<?php

namespace App\Livewire\Athlete;

use App\Data\Exercise\ExerciseSetting;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Models\Training\TrainingProgramSlotSetValue;
use App\Support\AthleteDashboardDate;
use App\Training\ExerciseGroupLabeler;
use App\Training\TrainingSessionMaterializer;
use App\Training\TrainingSessionProgressService;
use Carbon\CarbonImmutable;
use Coda\Cms\Support\ColorPalette;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ProgramDetails extends Component
{
    private const SECTION_ORDER = [
        'warm_up',
        'main',
        'warm_down',
    ];

    private const SECTION_LABELS = [
        'warm_up' => 'Warm Up',
        'main' => 'Program',
        'warm_down' => 'Warm Down',
    ];

    private const SETTING_PRIORITY = [
        'reps',
        'weight',
        'distance',
        'duration',
        'pace',
        'watts',
        'heartRate',
        'heartRateZone',
        'tempo',
        'rest',
        'note',
    ];

    private const OPAQUE_ZONE_COLORS = [
        '0' => 'bg-zinc-200 dark:bg-zinc-800',
        '1' => 'bg-green-200 dark:bg-green-900',
        '2' => 'bg-yellow-200 dark:bg-yellow-900',
        '3' => 'bg-red-200 dark:bg-red-900',
        '4' => 'bg-zinc-900 text-white dark:bg-black dark:text-white',
    ];

    public string $date;

    public int $trainingProgramId;

    public ?string $from = null;

    public string $activeSection = 'main';

    public function mount(string $date, TrainingProgram $trainingProgram): void
    {
        $this->date = CarbonImmutable::parse($date)->format('Y-m-d');
        $this->trainingProgramId = $trainingProgram->id;
        $this->from = $this->sanitizeReturnUrl(request()->query('from'));
        $this->activeSection = 'main';

        abort_unless(
            TrainingProgramSlot::query()
                ->where('training_program_id', $this->trainingProgramId)
                ->where('user_id', auth()->id())
                ->whereDate('datetime', $this->date)
                ->exists(),
            404
        );
    }

    #[Computed]
    public function trainingProgram(): TrainingProgram
    {
        return $this->currentSlot->trainingProgram;
    }

    #[Computed]
    public function currentSlot(): TrainingProgramSlot
    {
        $slot = TrainingProgramSlot::query()
            ->with([
                'trainingProgram.program.exerciseCategory',
                'trainingProgram.program.exercises',
                'exercises.exercise.category',
                'exercises.exercise.equipment',
                'exercises.exercise.modifiers',
                'exercises.exercise.media',
                'exercises.sets.values',
            ])
            ->where('training_program_id', $this->trainingProgramId)
            ->where('user_id', auth()->id())
            ->whereDate('datetime', $this->date)
            ->orderBy('datetime')
            ->orderBy('id')
            ->firstOrFail();

        if ($slot->compiled_at === null) {
            app(TrainingSessionMaterializer::class)->materialize($slot, force: true);

            $slot = $slot->fresh([
                'trainingProgram.program.exerciseCategory',
                'trainingProgram.program.exercises',
                'exercises.exercise.category',
                'exercises.exercise.equipment',
                'exercises.exercise.modifiers',
                'exercises.exercise.media',
                'exercises.sets.values',
            ]);
        } elseif ($this->shouldRefreshAuxiliarySections($slot)) {
            app(TrainingSessionMaterializer::class)->materialize($slot, force: true);

            $slot = $slot->fresh([
                'trainingProgram.program.exerciseCategory',
                'trainingProgram.program.exercises',
                'exercises.exercise.category',
                'exercises.exercise.equipment',
                'exercises.exercise.modifiers',
                'exercises.exercise.media',
                'exercises.sets.values',
            ]);
        }

        return $slot;
    }

    #[Computed]
    public function programExercises(): array
    {
        $sorted = $this->currentSlot->exercises
            ->where('type', $this->activeSection)
            ->sortBy('sort')
            ->values();

        $materializedGroupLabels = ExerciseGroupLabeler::label(
            $sorted,
            fn (TrainingProgramSlotExercise $exercise): ?string => $exercise->group,
            fn (TrainingProgramSlotExercise $exercise): int => $exercise->id,
        );

        $sourceExercises = $this->trainingProgram->program->exercises()
            ->wherePivot('type', $this->activeSection)
            ->orderByPivot('sort')
            ->orderByPivot('id')
            ->get()
            ->values();

        $sourceGroupLabelsByIndex = ExerciseGroupLabeler::label(
            $sourceExercises,
            fn ($exercise): ?string => $exercise->pivot->group,
            fn ($exercise): int => $sourceExercises->search($exercise),
        );

        return $sorted
            ->map(fn (TrainingProgramSlotExercise $exercise, int $index) => $this->buildExerciseViewData(
                $exercise,
                $index,
                $materializedGroupLabels[$exercise->id] ?? $sourceGroupLabelsByIndex[$index] ?? null,
            ))
            ->values()
            ->all();
    }

    #[Computed]
    public function sectionTabs(): array
    {
        $tabs = collect(self::SECTION_ORDER)
            ->map(function (string $section): ?array {
                $count = $this->currentSlot->exercises
                    ->where('type', $section)
                    ->count();

                if ($count === 0) {
                    return null;
                }

                return [
                    'key' => $section,
                    'label' => self::SECTION_LABELS[$section] ?? Str::headline($section),
                    'count' => $count,
                ];
            })
            ->filter()
            ->values()
            ->all();

        if (! collect($tabs)->pluck('key')->contains($this->activeSection)) {
            $this->activeSection = collect($tabs)->pluck('key')->contains('main')
                ? 'main'
                : (collect($tabs)->first()['key'] ?? 'main');
        }

        return $tabs;
    }

    #[Computed]
    public function showsSectionTabs(): bool
    {
        return collect($this->sectionTabs)
            ->pluck('key')
            ->contains(fn (string $key): bool => $key !== 'main');
    }

    /**
     * @return array<int, array{submitted: bool, color: array{light: string, dark: string}}>
     */
    #[Computed]
    public function progressSegments(): array
    {
        return $this->currentSlot->exercises
            ->sortBy('sort')
            ->values()
            ->map(fn (TrainingProgramSlotExercise $exercise) => [
                'submitted' => $exercise->status->isSubmitted(),
                'color' => $exercise->status->barColor(),
            ])
            ->all();
    }

    #[Computed]
    public function completionPercent(): int
    {
        $segments = $this->progressSegments;
        $total = count($segments);

        if ($total === 0) {
            return 0;
        }

        $submitted = array_filter($segments, fn (array $segment): bool => $segment['submitted']);

        return (int) round((count($submitted) / $total) * 100);
    }

    #[Computed]
    public function backUrl(): string
    {
        return $this->from ?: route('athlete.dashboard.calendar', ['date' => $this->date]);
    }

    #[Computed]
    public function backLabel(): string
    {
        $path = parse_url($this->backUrl, PHP_URL_PATH) ?: '';

        return Str::startsWith($path, '/dashboard/calendar')
            ? 'Back to Calendar'
            : 'Back to Dashboard';
    }

    public function markExerciseCompleted(int $slotExerciseId): void
    {
        abort_if($this->isFutureSession, 403);

        $exercise = $this->currentSlot->exercises->firstWhere('id', $slotExerciseId);
        abort_unless($exercise instanceof TrainingProgramSlotExercise, 404);

        app(TrainingSessionProgressService::class)->markExerciseCompleted($exercise);

        unset($this->currentSlot, $this->programExercises, $this->progressSegments, $this->sectionTabs, $this->showsSectionTabs);
    }

    public function markExerciseSkipped(int $slotExerciseId): void
    {
        abort_if($this->isFutureSession, 403);

        $exercise = $this->currentSlot->exercises->firstWhere('id', $slotExerciseId);
        abort_unless($exercise instanceof TrainingProgramSlotExercise, 404);

        app(TrainingSessionProgressService::class)->markExerciseSkipped($exercise);

        unset($this->currentSlot, $this->programExercises, $this->progressSegments, $this->sectionTabs, $this->showsSectionTabs);
    }

    protected function shouldRefreshAuxiliarySections(TrainingProgramSlot $slot): bool
    {
        $sourceHasAuxiliarySections = $slot->trainingProgram->program->exercises
            ->contains(fn ($exercise): bool => ($exercise->pivot->type ?? 'main') !== 'main');

        if (! $sourceHasAuxiliarySections) {
            return false;
        }

        $slotHasAuxiliarySections = $slot->exercises
            ->contains(fn (TrainingProgramSlotExercise $exercise): bool => in_array($exercise->type ?? 'main', ['warm_up', 'warm_down'], true));

        if ($slotHasAuxiliarySections) {
            return false;
        }

        return (int) $slot->completed_exercise_count === 0
            && (int) $slot->partial_exercise_count === 0
            && (int) $slot->skipped_exercise_count === 0
            && ! $slot->has_any_modification;
    }

    #[Computed]
    public function isFutureSession(): bool
    {
        return AthleteDashboardDate::isFutureDate($this->date);
    }

    protected function buildExerciseViewData(TrainingProgramSlotExercise $slotExercise, int $index, ?string $groupLabel = null): array
    {
        $exercise = $slotExercise->exercise;
        $sets = $slotExercise->sets->sortBy('set_number')->values();
        $settingKeys = $this->orderedSettings(
            $sets->flatMap(fn ($set) => $set->values->pluck('setting_key'))
                ->unique()
                ->values()
                ->all()
        );

        $sessionRows = [];
        $sessionNotes = [];
        $colorIndex = 0;

        foreach ($settingKeys as $setting) {
            if ($setting === 'note') {
                $notes = $sets
                    ->map(fn ($set) => $this->extractPlannedValue($set->values->firstWhere('setting_key', 'note')))
                    ->filter(fn ($value) => ! $this->isBlankValue($value))
                    ->unique()
                    ->values();

                if ($notes->isNotEmpty()) {
                    $sessionNotes[] = [
                        'label' => 'Note',
                        'value' => $notes->implode(' / '),
                    ];
                }

                continue;
            }

            $rowColorName = ColorPalette::ROW_COLORS[$colorIndex] ?? null;
            $labelClass = $this->opaqueRowLabelClass($rowColorName);
            $values = [];
            $valueClasses = [];
            $firstValueRow = null;

            foreach ($sets as $set) {
                $valueRow = $set->values->firstWhere('setting_key', $setting);
                if ($valueRow instanceof TrainingProgramSlotSetValue && $firstValueRow === null) {
                    $firstValueRow = $valueRow;
                }

                $rawValue = $this->extractPlannedValue($valueRow);
                $values[] = $this->formatSessionValue($setting, $rawValue);
                $valueClasses[] = $this->opaqueCellClass($setting, $rawValue, $rowColorName);
            }

            if (collect($values)->every(fn (?string $value) => $value === null)) {
                continue;
            }

            $sessionRows[] = [
                'label' => $this->resolveMaterializedSettingLabel($setting, $firstValueRow),
                'values' => $values,
                'labelClass' => $labelClass,
                'valueClasses' => $valueClasses,
            ];

            $colorIndex++;
        }

        return [
            'id' => $slotExercise->id,
            'index' => $index + 1,
            'groupLabel' => $groupLabel,
            'name' => $exercise?->name ?? 'Exercise',
            'equipmentBadges' => $exercise?->equipment?->pluck('name')->filter()->values()->all() ?? [],
            'modifierBadges' => $exercise?->modifiers?->pluck('name')->filter()->values()->all() ?? [],
            'instructions' => $exercise?->instructions,
            'videoUrl' => $exercise?->video_url,
            'photoUrls' => $exercise?->getMedia('photos')->map(fn ($media) => $media->getUrl())->values()->all() ?? [],
            'setLabel' => $exercise?->config->sets->label ?? 'Set',
            'setCount' => $sets->count(),
            'sessionRows' => $sessionRows,
            'weekDetails' => [],
            'notes' => $sessionNotes,
            'status' => $slotExercise->status,
            'statusLabel' => $slotExercise->status->label(),
            'statusColor' => $slotExercise->status->barColor(),
        ];
    }

    protected function formatSessionValue(string $setting, mixed $value): ?string
    {
        if ($this->isBlankValue($value)) {
            return null;
        }

        return match ($setting) {
            'heartRateZone' => 'Zone '.trim((string) $value),
            default => $this->normalizeScalar($value),
        };
    }

    protected function resolveMaterializedSettingLabel(string $setting, ?TrainingProgramSlotSetValue $valueRow): string
    {
        $enum = ExerciseSetting::tryFrom($setting);
        $label = $enum?->shortLabel() ?? ucfirst($setting);
        $unit = $valueRow?->unit;

        if ($unit && ($enum?->showsUnitInLabel() ?? true)) {
            return "{$label} ({$unit})";
        }

        return $label;
    }

    protected function opaqueRowLabelClass(?string $color): string
    {
        return $color
            ? ColorPalette::lightOpaque($color)
            : 'bg-zinc-300 dark:bg-zinc-900';
    }

    protected function opaqueCellClass(string $setting, mixed $value, ?string $rowColor): string
    {
        if ($setting === 'heartRateZone') {
            $zone = trim((string) $value);

            return self::OPAQUE_ZONE_COLORS[$zone] ?? $this->opaqueRowLabelClass($rowColor);
        }

        return $this->opaqueRowLabelClass($rowColor);
    }

    protected function normalizeScalar(mixed $value): string
    {
        if (is_float($value)) {
            return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_numeric($value) && str_contains((string) $value, '.')) {
            return rtrim(rtrim(number_format((float) $value, 1, '.', ''), '0'), '.');
        }

        return trim((string) $value);
    }

    protected function extractPlannedValue(?TrainingProgramSlotSetValue $valueRow): mixed
    {
        if (! $valueRow) {
            return null;
        }

        return match ($valueRow->planned_value_type) {
            'int' => $valueRow->planned_int_value,
            'decimal' => $valueRow->planned_decimal_value !== null ? (float) $valueRow->planned_decimal_value : null,
            'json' => $valueRow->planned_json_value,
            default => $valueRow->planned_string_value,
        };
    }

    protected function orderedSettings(array $settings): Collection
    {
        return collect($settings)
            ->unique()
            ->sortBy(function (string $setting): int {
                $priority = array_search($setting, self::SETTING_PRIORITY, true);

                return $priority === false ? PHP_INT_MAX : $priority;
            })
            ->values();
    }

    protected function isBlankValue(mixed $value): bool
    {
        return $value === null || trim((string) $value) === '' || $value === '-';
    }

    protected function sanitizeReturnUrl(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        if (Str::startsWith($url, '/')) {
            return $url;
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $appUrl = parse_url(url('/'));
        $returnUrl = parse_url($url);

        if (($appUrl['host'] ?? null) !== ($returnUrl['host'] ?? null)) {
            return null;
        }

        $path = $returnUrl['path'] ?? '/';
        $query = isset($returnUrl['query']) ? '?'.$returnUrl['query'] : '';
        $fragment = isset($returnUrl['fragment']) ? '#'.$returnUrl['fragment'] : '';

        return $path.$query.$fragment;
    }

    public function categoryBadgeStyle(?string $color): ?string
    {
        if (! is_string($color) || trim($color) === '') {
            return null;
        }

        $rawColor = trim($color);
        $normalized = ltrim($rawColor, '#');

        if (preg_match('/^[0-9a-fA-F]{3}$/', $normalized)) {
            $normalized = implode('', array_map(
                fn (string $char): string => $char.$char,
                str_split($normalized)
            ));
        }

        $textColor = '#ffffff';

        if (preg_match('/^[0-9a-fA-F]{6}$/', $normalized)) {
            $red = hexdec(substr($normalized, 0, 2));
            $green = hexdec(substr($normalized, 2, 2));
            $blue = hexdec(substr($normalized, 4, 2));
            $luminance = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;
            $textColor = $luminance > 160 ? '#111827' : '#ffffff';
            $rawColor = '#'.$normalized;
        } elseif (! preg_match('/^[#(),.%\-\sa-zA-Z0-9]+$/', $rawColor)) {
            return null;
        }

        return sprintf('background-color: %s; color: %s;', $rawColor, $textColor);
    }

    public function render(): View
    {
        return view('livewire.athlete.program-details', [
            'trainingProgram' => $this->trainingProgram,
            'currentSlot' => $this->currentSlot,
            'programExercises' => $this->programExercises,
            'sectionTabs' => $this->sectionTabs,
            'showsSectionTabs' => $this->showsSectionTabs,
            'isFutureSession' => $this->isFutureSession,
        ])->layout('components.layouts.athlete', ['title' => $this->trainingProgram->program->name]);
    }
}
