<?php

namespace App\Livewire\Athlete;

use App\Data\Athlete\Metric\MetricEnum;
use App\Data\Athlete\Metric\Metrics\HeartRateMetric;
use App\Data\Athlete\Metric\Metrics\OneRepMaxMetric;
use App\Data\Exercise\ExerciseSetting;
use App\Data\Exercise\Preview\GridOverrides;
use App\Data\Exercise\Preview\GridState;
use App\Data\Exercise\Preview\StrategyOrchestrator;
use App\Data\Exercise\Settings\WeightProgressionSetting;
use App\Data\Training\Config\EffectiveExerciseConfig;
use App\Models\Athlete\MetricSubmission;
use App\Models\Exercise\Exercise;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
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
        return TrainingProgram::query()
            ->with([
                'program.exerciseCategory',
                'program.exercises.category',
                'program.exercises.equipment',
                'program.exercises.modifiers',
                'program.exercises.media',
            ])
            ->findOrFail($this->trainingProgramId);
    }

    #[Computed]
    public function currentSlot(): TrainingProgramSlot
    {
        return TrainingProgramSlot::query()
            ->where('training_program_id', $this->trainingProgramId)
            ->where('user_id', auth()->id())
            ->whereDate('datetime', $this->date)
            ->orderBy('datetime')
            ->orderBy('id')
            ->firstOrFail();
    }

    #[Computed]
    public function sessionContext(): array
    {
        $slotIds = TrainingProgramSlot::query()
            ->where('training_program_id', $this->trainingProgramId)
            ->where('user_id', auth()->id())
            ->orderBy('datetime')
            ->orderBy('id')
            ->pluck('id')
            ->values();

        $slotIndex = $slotIds->search($this->currentSlot->id);
        $slotIndex = $slotIndex === false ? 0 : (int) $slotIndex;

        $sessionsPerWeek = max(1, (int) ($this->trainingProgram->program->config->preview->sessionsPerWeek ?? 1));

        return [
            'slotIndex' => $slotIndex,
            'weekIndex' => intdiv($slotIndex, $sessionsPerWeek),
            'sessionIndex' => $slotIndex % $sessionsPerWeek,
            'sessionsPerWeek' => $sessionsPerWeek,
        ];
    }

    #[Computed]
    public function oneRepMaxMetric(): ?OneRepMaxMetric
    {
        $submission = $this->latestMetricSubmission(MetricEnum::OneRepMax);

        if (! $submission) {
            return null;
        }

        return OneRepMaxMetric::from($submission->values->pluck('value', 'field')->all());
    }

    #[Computed]
    public function heartRateMetric(): ?HeartRateMetric
    {
        $submission = $this->latestMetricSubmission(MetricEnum::HeartRate);

        if (! $submission) {
            return null;
        }

        return HeartRateMetric::from($submission->values->pluck('value', 'field')->all());
    }

    #[Computed]
    public function programExercises(): array
    {
        $programConfig = $this->trainingProgram->program->config;
        $sessionContext = $this->sessionContext;

        return $this->trainingProgram->program->exercises
            ->map(fn (Exercise $exercise, int $index) => $this->buildExerciseViewData($exercise, $programConfig, $sessionContext, $index))
            ->filter()
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

    protected function buildExerciseViewData(Exercise $exercise, mixed $programConfig, array $sessionContext, int $index): ?array
    {
        $planOverrides = $programConfig->defaultExerciseOverrides($exercise->id);
        $userOverrides = $programConfig->userExerciseOverrides((int) auth()->id(), $exercise->id);

        if (EffectiveExerciseConfig::resolveDisabled($planOverrides, $userOverrides)) {
            return null;
        }

        $effectiveConfig = EffectiveExerciseConfig::resolve($exercise->config, $planOverrides, $userOverrides);
        $overrideLayer = EffectiveExerciseConfig::resolveForLayer($exercise->config, $planOverrides, $userOverrides);

        $weeks = max(
            (int) ($effectiveConfig['preview']['weeks'] ?? 1),
            $sessionContext['weekIndex'] + 1
        );

        $orchestrator = new StrategyOrchestrator(
            data: $effectiveConfig,
            measuredData: $this->resolveWeightProgressionData(),
            weeks: $weeks,
            overrides: GridOverrides::fromArrays(
                $overrideLayer['cells'] ?? [],
                $overrideLayer['weeks'] ?? [],
            ),
            maxHR: $this->heartRateMetric?->heartRate,
            iatPercent: $this->heartRateMetric?->anaerobicThreshold,
        );

        $state = $orchestrator->execute();
        $weekIndex = $sessionContext['weekIndex'];
        $setCount = (int) ($state->getSetsPerWeek()[$weekIndex] ?? ($effectiveConfig['sets']['default'] ?? 0));

        $sessionRows = [];
        $sessionNotes = [];
        $weekDetails = [];
        $colorIndex = 0;

        foreach ($this->orderedSettings($effectiveConfig['settings'] ?? []) as $setting) {
            $config = $effectiveConfig[$setting] ?? [];
            $applyPer = $config['applyPer'] ?? 'session';
            $isTableSetting = in_array($setting, ['tempo', 'rest'], true);

            if ($setting === 'note') {
                $noteValue = $applyPer === 'week'
                    ? $this->resolveWeekValue($state, $setting, $config, $weekIndex)
                    : $this->resolveSessionNote($state, $setting, $config, $weekIndex, $setCount);

                if (! $this->isBlankValue($noteValue)) {
                    $sessionNotes[] = [
                        'label' => ($config['label'] ?? '') ?: 'Note',
                        'value' => (string) $noteValue,
                    ];
                }

                continue;
            }

            if ($applyPer === 'week' && ! $isTableSetting) {
                $value = $this->resolveWeekValue($state, $setting, $config, $weekIndex);

                if (! $this->isBlankValue($value)) {
                    $weekDetails[] = $this->buildWeekDetailLabel($setting, $config, $value);
                }

                continue;
            }

            $values = [];

            for ($set = 0; $set < $setCount; $set++) {
                $rawValue = $applyPer === 'week'
                    ? $this->resolveWeekValue($state, $setting, $config, $weekIndex)
                    : $this->resolveSessionValue($state, $setting, $config, $weekIndex, $set);

                $values[] = $this->formatSessionValue($setting, $rawValue);
            }

            if (collect($values)->every(fn (?string $value) => $value === null)) {
                continue;
            }

            $rowColorName = ColorPalette::ROW_COLORS[$colorIndex] ?? null;
            $rowClass = $this->opaqueRowClass($rowColorName);
            $labelClass = $this->opaqueRowLabelClass($rowColorName);
            $valueClasses = [];

            for ($set = 0; $set < $setCount; $set++) {
                $rawValue = $this->resolveSessionValue($state, $setting, $config, $weekIndex, $set);
                $valueClasses[] = $this->opaqueCellClass($setting, $rawValue, $rowColorName);
            }

            $sessionRows[] = [
                'label' => $this->resolveSettingLabel($setting, $config),
                'values' => $values,
                'rowClass' => $rowClass,
                'labelClass' => $labelClass,
                'valueClasses' => $valueClasses,
            ];

            $colorIndex++;
        }

        return [
            'index' => $index + 1,
            'name' => $exercise->name,
            'category' => $exercise->category?->name,
            'categoryColor' => $exercise->category?->color,
            'equipmentBadges' => $exercise->equipment->pluck('name')->filter()->values()->all(),
            'modifierBadges' => $exercise->modifiers->pluck('name')->filter()->values()->all(),
            'instructions' => $exercise->instructions,
            'videoUrl' => $exercise->video_url,
            'photoUrls' => $exercise->getMedia('photos')->map(fn ($media) => $media->getUrl())->values()->all(),
            'setLabel' => $effectiveConfig['sets']['label'] ?? 'Set',
            'setCount' => $setCount,
            'sessionRows' => $sessionRows,
            'weekDetails' => $weekDetails,
            'notes' => $sessionNotes,
        ];
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

    protected function resolveWeightProgressionData(): ?WeightProgressionSetting
    {
        if (! $this->oneRepMaxMetric) {
            return null;
        }

        return new WeightProgressionSetting(
            measuredReps: $this->oneRepMaxMetric->measuredReps,
            measuredWeight: $this->oneRepMaxMetric->measuredWeight,
            targetGoal: $this->trainingProgram->program->config->defaultTargetGoal(),
        );
    }

    protected function latestMetricSubmission(MetricEnum $metric): ?MetricSubmission
    {
        return MetricSubmission::query()
            ->forAthlete((int) auth()->id())
            ->forMetric($metric)
            ->whereDate('recorded_at', '<=', $this->date)
            ->manual()
            ->with('values')
            ->latest('recorded_at')
            ->latest('id')
            ->first();
    }

    protected function resolveSessionValue(GridState $state, string $setting, array $config, int $weekIndex, int $setIndex): mixed
    {
        $resolved = $state->getResolvedCellValue($setting, $weekIndex, $setIndex);

        if ($resolved !== null) {
            return $resolved;
        }

        return $this->defaultValue($config);
    }

    protected function resolveSessionNote(mixed $state, string $setting, array $config, int $weekIndex, int $setCount): mixed
    {
        if ($setCount < 1) {
            return $this->defaultValue($config);
        }

        $values = collect(range(0, max($setCount - 1, 0)))
            ->map(fn (int $setIndex) => $this->resolveSessionValue($state, $setting, $config, $weekIndex, $setIndex))
            ->filter(fn (mixed $value) => ! $this->isBlankValue($value))
            ->unique()
            ->values();

        if ($values->isEmpty()) {
            return $this->defaultValue($config);
        }

        return $values->implode(' / ');
    }

    protected function resolveWeekValue(mixed $state, string $setting, array $config, int $weekIndex): mixed
    {
        return $state->getResolvedWeekValue($setting, $weekIndex, $this->defaultValue($config));
    }

    protected function defaultValue(array $config): mixed
    {
        $default = $config['default'] ?? null;

        return $this->isBlankValue($default) ? null : $default;
    }

    protected function resolveSettingLabel(string $setting, array $config): string
    {
        if (! empty($config['label'])) {
            return $config['label'];
        }

        $enum = ExerciseSetting::tryFrom($setting);
        $label = $enum?->label() ?? ucfirst($setting);

        if ($enum?->settingClass()) {
            $unit = $enum->settingClass()::resolveUnitLabel($config);

            if ($unit !== null) {
                return "{$label} ({$unit})";
            }
        }

        return $label;
    }

    protected function opaqueRowClass(?string $color): string
    {
        return $color
            ? ColorPalette::lightOpaqueSubtle($color)
            : 'bg-zinc-200 dark:bg-zinc-800';
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

    protected function buildWeekDetailLabel(string $setting, array $config, mixed $value): string
    {
        $label = ($config['label'] ?? '') ?: (ExerciseSetting::tryFrom($setting)?->label() ?? ucfirst($setting));
        $formattedValue = $this->formatBadgeValue($setting, $value, $config);

        return trim("{$label} {$formattedValue}");
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

    protected function formatBadgeValue(string $setting, mixed $value, array $config): string
    {
        if ($this->isBlankValue($value)) {
            return '';
        }

        return match ($setting) {
            'heartRate' => $this->normalizeScalar($value).' bpm',
            'heartRateZone' => 'Zone '.$this->normalizeScalar($value),
            'rest' => $this->normalizeScalar($value).'s',
            'weight' => $this->normalizeScalar($value).'kg',
            'pace' => $this->normalizeScalar($value).'/km',
            default => $this->normalizeScalar($value),
        };
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
            'programExercises' => $this->programExercises,
        ])->layout('components.layouts.athlete', ['title' => $this->trainingProgram->program->name]);
    }
}
