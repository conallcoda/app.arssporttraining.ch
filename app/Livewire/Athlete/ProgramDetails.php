<?php

namespace App\Livewire\Athlete;

use App\Data\Exercise\ExerciseSetting;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Models\Training\TrainingProgramSlotExerciseStatusEnum;
use App\Models\Training\TrainingProgramSlotSetValue;
use App\Models\Training\TrainingProgramSlotStatusEnum;
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

    public function mount(string $date, TrainingProgram $trainingProgram): void
    {
        $this->date = CarbonImmutable::parse($date)->format('Y-m-d');
        $this->trainingProgramId = $trainingProgram->id;
        $this->from = $this->sanitizeReturnUrl(request()->query('from'));

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
        return $this->currentSlot->exercises
            ->sortBy('sort')
            ->values()
            ->map(fn (TrainingProgramSlotExercise $exercise, int $index) => $this->buildExerciseViewData($exercise, $index))
            ->values()
            ->all();
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
        $exercise = $this->currentSlot->exercises->firstWhere('id', $slotExerciseId);
        abort_unless($exercise instanceof TrainingProgramSlotExercise, 404);

        app(TrainingSessionProgressService::class)->markExerciseCompleted($exercise);

        unset($this->currentSlot, $this->programExercises);
    }

    public function markExerciseSkipped(int $slotExerciseId): void
    {
        $exercise = $this->currentSlot->exercises->firstWhere('id', $slotExerciseId);
        abort_unless($exercise instanceof TrainingProgramSlotExercise, 404);

        app(TrainingSessionProgressService::class)->markExerciseSkipped($exercise);

        unset($this->currentSlot, $this->programExercises);
    }

    protected function buildExerciseViewData(TrainingProgramSlotExercise $slotExercise, int $index): array
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
            'name' => $exercise?->name ?? 'Exercise',
            'category' => $exercise?->category?->name,
            'categoryColor' => $exercise?->category?->color,
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
            'statusLabel' => $this->exerciseStatusLabel($slotExercise->status),
            'statusClass' => $this->exerciseStatusClass($slotExercise->status),
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
        $label = $enum?->label() ?? ucfirst($setting);
        $unit = $valueRow?->unit;

        return $unit ? "{$label} ({$unit})" : $label;
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

    public function slotStatusLabel(): string
    {
        return match ($this->currentSlot->status) {
            TrainingProgramSlotStatusEnum::Completed => 'Completed',
            TrainingProgramSlotStatusEnum::PartiallyCompleted => 'Partially Completed',
            TrainingProgramSlotStatusEnum::Skipped => 'Skipped',
            TrainingProgramSlotStatusEnum::Cancelled => 'Cancelled',
            default => 'Pending',
        };
    }

    public function slotStatusClass(): string
    {
        return match ($this->currentSlot->status) {
            TrainingProgramSlotStatusEnum::Completed => 'bg-green-100 text-green-800 dark:bg-green-900/60 dark:text-green-100',
            TrainingProgramSlotStatusEnum::PartiallyCompleted => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/60 dark:text-yellow-100',
            TrainingProgramSlotStatusEnum::Skipped => 'bg-zinc-200 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-100',
            TrainingProgramSlotStatusEnum::Cancelled => 'bg-red-100 text-red-800 dark:bg-red-900/60 dark:text-red-100',
            default => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-100',
        };
    }

    protected function exerciseStatusLabel(TrainingProgramSlotExerciseStatusEnum $status): string
    {
        return match ($status) {
            TrainingProgramSlotExerciseStatusEnum::Completed => 'Completed',
            TrainingProgramSlotExerciseStatusEnum::PartiallyCompleted => 'Partially Completed',
            TrainingProgramSlotExerciseStatusEnum::Skipped => 'Skipped',
            default => 'Pending',
        };
    }

    protected function exerciseStatusClass(TrainingProgramSlotExerciseStatusEnum $status): string
    {
        return match ($status) {
            TrainingProgramSlotExerciseStatusEnum::Completed => 'bg-green-100 text-green-800 dark:bg-green-900/60 dark:text-green-100',
            TrainingProgramSlotExerciseStatusEnum::PartiallyCompleted => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/60 dark:text-yellow-100',
            TrainingProgramSlotExerciseStatusEnum::Skipped => 'bg-zinc-200 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-100',
            default => 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-100',
        };
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
        ])->layout('components.layouts.athlete', ['title' => $this->trainingProgram->program->name]);
    }
}
