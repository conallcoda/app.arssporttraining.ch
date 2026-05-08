<?php

namespace App\Livewire\Athlete;

use App\Data\Athlete\ProgramDetailsExerciseData;
use App\Data\Exercise\ExerciseSetting;
use App\Data\Exercise\Settings\AbstractSetting;
use App\Models\Training\TrainingProgram;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Training\TrainingProgramSlotExercise;
use App\Models\Training\TrainingProgramSlotSet;
use App\Models\Training\TrainingProgramSlotSetStatusEnum;
use App\Support\AthleteDashboardDate;
use App\Support\Athlete\ProgramDetailsExerciseViewBuilder;
use App\Training\AthleteExerciseValueService;
use App\Training\ExerciseGroupLabeler;
use App\Training\TrainingSessionMaterializer;
use App\Training\TrainingSessionProgressService;
use Carbon\CarbonImmutable;
use Coda\FormKit\Field;
use Flux\Flux;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
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

    public string $date;

    public int $trainingProgramId;

    public ?string $from = null;

    public string $activeSection = 'main';

    public ?int $editingExerciseId = null;

    public string $activeEditSet = '';

    public array $editValues = [];

    public array $editSkippedSets = [];

    public array $pendingSkippedSets = [];

    public bool $previewMode = false;

    public ?int $previewUserId = null;

    public function mount(string $date, ?TrainingProgram $trainingProgram = null): void
    {
        $this->date = CarbonImmutable::parse($date)->format('Y-m-d');
        $this->from = $this->sanitizeReturnUrl(request()->query('from'));

        abort_unless($trainingProgram instanceof TrainingProgram, 404);
        $this->trainingProgramId = $trainingProgram->id;

        abort_unless(
            TrainingProgramSlot::query()
                ->where('training_program_id', $this->trainingProgramId)
                ->where('user_id', $this->sessionUserId())
                ->whereDate('datetime', $this->date)
                ->exists(),
            404
        );

        $this->activeSection = $this->resolveInitialSection($trainingProgram);
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
            ->where('user_id', $this->sessionUserId())
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
            ->map(fn (TrainingProgramSlotExercise $exercise, int $index) => app(ProgramDetailsExerciseViewBuilder::class)->build(
                $exercise,
                $index,
                $materializedGroupLabels[$exercise->id] ?? $sourceGroupLabelsByIndex[$index] ?? null,
                $this->pendingSkippedSets[$exercise->id] ?? [],
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

    private function resolveInitialSection(TrainingProgram $trainingProgram): string
    {
        foreach (self::SECTION_ORDER as $section) {
            if ($trainingProgram->program->exercises()->wherePivot('type', $section)->exists()) {
                return $section;
            }
        }

        return 'main';
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
        if ($this->previewMode) {
            return '#';
        }

        return $this->from ?: route('athlete.dashboard.train', ['date' => $this->date]);
    }

    #[Computed]
    public function backLabel(): string
    {
        if ($this->previewMode) {
            return 'Back to Preview';
        }

        return 'Back to Dashboard';
    }

    #[Computed]
    public function athleteEditsEnabled(): bool
    {
        if ($this->previewMode) {
            return false;
        }

        return (bool) config('athlete.allow_athlete_edits', false);
    }

    #[Computed]
    public function canRecordSession(): bool
    {
        if ($this->previewMode) {
            return false;
        }

        return AthleteDashboardDate::canRecordProgramExercisesForDate($this->date);
    }

    #[Computed]
    public function editingExercise(): ?TrainingProgramSlotExercise
    {
        if ($this->editingExerciseId === null) {
            return null;
        }

        $exercise = $this->currentSlot->exercises->firstWhere('id', $this->editingExerciseId);

        return $exercise instanceof TrainingProgramSlotExercise ? $exercise : null;
    }

    #[Computed]
    public function editSetTabs(): array
    {
        return collect($this->editingExercise?->sets ?? [])
            ->sortBy('set_number')
            ->values()
            ->map(fn ($set): array => [
                'name' => 'set-'.$set->id,
                'label' => $this->editSetLabel($set->set_number),
                'isSkipped' => (bool) ($this->editSkippedSets[$set->id] ?? false),
            ])
            ->all();
    }

    #[Computed]
    public function editSetPanels(): array
    {
        $exercise = $this->editingExercise;

        if (! $exercise instanceof TrainingProgramSlotExercise) {
            return [];
        }

        return $exercise->sets
            ->sortBy('set_number')
            ->values()
            ->map(function ($set) use ($exercise): array {
                $fields = collect($set->values)
                    ->sortBy(fn ($value) => $this->settingPriority($value->setting_key))
                    ->map(function ($value) use ($exercise): ?Field {
                        $settingClass = ExerciseSetting::tryFrom($value->setting_key)?->settingClass();

                        if (! is_string($settingClass) || ! is_subclass_of($settingClass, AbstractSetting::class)) {
                            return null;
                        }

                        return $settingClass::athleteField(
                            $value->setting_key,
                            $this->resolveSettingConfig($exercise, $value->setting_key)
                        );
                    })
                    ->filter()
                    ->values()
                    ->all();

                return [
                    'id' => $set->id,
                    'tab' => 'set-'.$set->id,
                    'label' => $this->editSetLabel($set->set_number),
                    'isSkipped' => (bool) ($this->editSkippedSets[$set->id] ?? false),
                    'fields' => $fields,
                ];
            })
            ->all();
    }

    public function openExerciseEditor(int $slotExerciseId): void
    {
        abort_if(! $this->canRecordSession, 403);

        $exercise = $this->currentSlot->exercises->firstWhere('id', $slotExerciseId);
        abort_unless($exercise instanceof TrainingProgramSlotExercise, 404);

        abort_if(! $this->athleteEditsEnabled, 404);

        $this->prepareExerciseEditor($exercise);
        Flux::modal('athlete-exercise-editor')->show();
    }

    public function saveExerciseEdits(): void
    {
        abort_if(! $this->athleteEditsEnabled, 404);
        abort_if(! $this->canRecordSession, 403);

        $exercise = $this->editingExercise;
        abort_unless($exercise instanceof TrainingProgramSlotExercise, 404);

        $rules = $this->buildEditValidationRules();

        if ($rules !== []) {
            $this->validate(
                $rules,
                ['required' => 'This field is required.'],
                $this->buildEditValidationAttributes()
            );
        }

        $this->editValues = $this->castEmptyStringsToNull($this->editValues);

        app(AthleteExerciseValueService::class)->saveExerciseValues($exercise, $this->filterEditableSetValues($this->editValues));
        $this->pendingSkippedSets[$exercise->id] = $this->normalizeSkippedDraft($this->editSkippedSets);

        Flux::modal('athlete-exercise-editor')->close();

        $this->resetEditorState();
        unset($this->programExercises);
    }

    public function cancelExerciseEditor(): void
    {
        $this->resetEditorState();
        Flux::modal('athlete-exercise-editor')->close();
    }

    public function markEditSetSkipped(int $setId): void
    {
        abort_if(! $this->athleteEditsEnabled, 404);
        abort_if(! $this->canRecordSession, 403);

        $this->editSkippedSets[$setId] = true;
        unset($this->editingExercise, $this->editSetTabs, $this->editSetPanels);
    }

    public function markEditSetPending(int $setId): void
    {
        abort_if(! $this->athleteEditsEnabled, 404);
        abort_if(! $this->canRecordSession, 403);

        $this->editSkippedSets[$setId] = false;
        unset($this->editingExercise, $this->editSetTabs, $this->editSetPanels);
    }

    public function markExerciseCompleted(int $slotExerciseId): void
    {
        abort_if($this->previewMode, 403);
        abort_if(! $this->canRecordSession, 403);

        $exercise = $this->currentSlot->exercises->firstWhere('id', $slotExerciseId);
        abort_unless($exercise instanceof TrainingProgramSlotExercise, 404);

        if ($this->pendingSkipDraftSkipsEntireExercise($exercise)) {
            unset($this->pendingSkippedSets[$slotExerciseId]);
            app(TrainingSessionProgressService::class)->markExerciseSkipped($exercise);
            $this->resetEditorState();
            $this->refreshSessionState();
            $this->dispatch('athlete-exercise-action-succeeded', exerciseId: $slotExerciseId);

            return;
        }

        if (! $this->validateExerciseCompletion($exercise)) {
            return;
        }

        $this->persistPendingSkippedSets($exercise);
        app(TrainingSessionProgressService::class)->markExerciseCompleted($exercise);
        unset($this->pendingSkippedSets[$slotExerciseId]);

        $this->resetEditorState();
        $this->refreshSessionState();
        $this->dispatch('athlete-exercise-action-succeeded', exerciseId: $slotExerciseId);
    }

    public function markExerciseSkipped(int $slotExerciseId): void
    {
        abort_if($this->previewMode, 403);
        abort_if(! $this->canRecordSession, 403);

        $exercise = $this->currentSlot->exercises->firstWhere('id', $slotExerciseId);
        abort_unless($exercise instanceof TrainingProgramSlotExercise, 404);

        unset($this->pendingSkippedSets[$slotExerciseId]);
        app(TrainingSessionProgressService::class)->markExerciseSkipped($exercise);

        $this->refreshSessionState();
        $this->dispatch('athlete-exercise-action-succeeded', exerciseId: $slotExerciseId);
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

    public function exitPreviewDetails(): void
    {
        if (! $this->previewMode) {
            return;
        }

        $this->dispatch('athlete-preview-back');
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

    protected function buildEditValues(TrainingProgramSlotExercise $exercise): array
    {
        return $exercise->sets
            ->sortBy('set_number')
            ->mapWithKeys(function ($set) use ($exercise): array {
                $values = $set->values
                    ->mapWithKeys(function ($value) use ($exercise): array {
                        $settingClass = ExerciseSetting::tryFrom($value->setting_key)?->settingClass();
                        $config = $this->resolveSettingConfig($exercise, $value->setting_key);
                        $resolvedValue = $this->extractResolvedValue($value);

                        return [$value->setting_key => $this->resolveEditInputValue(
                            $settingClass,
                            $resolvedValue,
                            $value->unit,
                            $config
                        )];
                    })
                    ->all();

                return [$set->id => $values];
            })
            ->all();
    }

    protected function buildEditSkippedSets(TrainingProgramSlotExercise $exercise): array
    {
        return $exercise->sets
            ->mapWithKeys(fn ($set): array => [
                $set->id => (string) ($set->status->value ?? $set->status) === TrainingProgramSlotSetStatusEnum::Skipped->value,
            ])
            ->all();
    }

    /**
     * @return array<string, string|array<int, string>>
     */
    protected function buildEditValidationRules(): array
    {
        $rules = [];

        foreach ($this->editSetPanels as $panel) {
            if ($panel['isSkipped']) {
                continue;
            }

            $rules = array_merge(
                $rules,
                Field::buildValidationRules(
                    $panel['fields'],
                    'editValues.'.$panel['id'].'.',
                    $this->editValues[$panel['id']] ?? []
                )
            );
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    protected function buildEditValidationAttributes(): array
    {
        $attributes = [];

        foreach ($this->editSetPanels as $panel) {
            if ($panel['isSkipped']) {
                continue;
            }

            $attributes = array_merge(
                $attributes,
                Field::buildValidationAttributes($panel['fields'], 'editValues.'.$panel['id'].'.')
            );
        }

        return $attributes;
    }

    protected function editSetLabel(int $setNumber): string
    {
        $baseLabel = $this->editingExercise?->exercise?->config?->sets->label ?? 'Set';

        return trim($baseLabel).' '.$setNumber;
    }

    protected function resolveSettingConfig(TrainingProgramSlotExercise $exercise, string $settingKey): array
    {
        $config = $exercise->exercise?->config?->{$settingKey} ?? null;

        return is_object($config) && method_exists($config, 'toArray')
            ? $config->toArray()
            : [];
    }

    protected function extractResolvedValue($value): mixed
    {
        if ($value->actual_value_type !== null) {
            return match ($value->actual_value_type) {
                'int' => $value->actual_int_value,
                'decimal' => $value->actual_decimal_value !== null ? (float) $value->actual_decimal_value : null,
                'json' => $value->actual_json_value,
                default => $value->actual_string_value,
            };
        }

        return match ($value->planned_value_type) {
            'int' => $value->planned_int_value,
            'decimal' => $value->planned_decimal_value !== null ? (float) $value->planned_decimal_value : null,
            'json' => $value->planned_json_value,
            default => $value->planned_string_value,
        };
    }

    protected function settingPriority(string $settingKey): int
    {
        $index = array_search($settingKey, self::SETTING_PRIORITY, true);

        return $index === false ? PHP_INT_MAX : $index;
    }

    protected function resolveEditInputValue(?string $settingClass, mixed $value, ?string $unit, array $config): mixed
    {
        if (! is_string($settingClass) || ! is_subclass_of($settingClass, AbstractSetting::class)) {
            return $value;
        }

        if (($config['unit'] ?? null) === 'mm:ss') {
            return $settingClass::formatAthleteValue($value, $unit, $config);
        }

        return $value;
    }

    protected function resetEditorState(): void
    {
        $this->editingExerciseId = null;
        $this->activeEditSet = '';
        $this->editValues = [];
        $this->editSkippedSets = [];

        unset($this->editingExercise, $this->editSetTabs, $this->editSetPanels);
    }

    protected function refreshSessionState(): void
    {
        unset($this->currentSlot, $this->programExercises, $this->progressSegments, $this->sectionTabs, $this->showsSectionTabs, $this->editingExercise, $this->editSetTabs, $this->editSetPanels);
    }

    protected function prepareExerciseEditor(TrainingProgramSlotExercise $exercise): void
    {
        $this->editingExerciseId = $exercise->id;
        $this->editValues = $this->buildEditValues($exercise);
        $this->editSkippedSets = $this->pendingSkippedSets[$exercise->id] ?? $this->buildEditSkippedSets($exercise);
        $this->activeEditSet = 'set-'.($exercise->sets->sortBy('set_number')->first()?->id ?? '');

        unset($this->editingExercise, $this->editSetTabs, $this->editSetPanels);
    }

    protected function validateExerciseCompletion(TrainingProgramSlotExercise $exercise): bool
    {
        $this->prepareExerciseEditor($exercise);
        $this->resetValidation();

        $rules = $this->buildEditValidationRules();

        if ($rules === []) {
            return true;
        }

        $validator = Validator::make(
            ['editValues' => $this->editValues],
            $rules,
            ['required' => 'This field is required.'],
            $this->buildEditValidationAttributes()
        );

        if ($validator->fails()) {
            $this->setErrorBag($validator->errors());
            $this->activateFirstInvalidEditSet(array_keys($validator->errors()->messages()));
            Flux::modal('athlete-exercise-editor')->show();

            return false;
        }

        return true;
    }

    /**
     * @param  array<int, string>  $errorKeys
     */
    protected function activateFirstInvalidEditSet(array $errorKeys): void
    {
        foreach ($errorKeys as $key) {
            if (! preg_match('/^editValues\.(\d+)\./', $key, $matches)) {
                continue;
            }

            $this->activeEditSet = 'set-'.$matches[1];

            return;
        }
    }

    protected function filterEditableSetValues(array $data): array
    {
        return collect($data)
            ->reject(fn ($_values, $setId): bool => (bool) ($this->editSkippedSets[(int) $setId] ?? false))
            ->all();
    }

    protected function normalizeSkippedDraft(array $draft): array
    {
        return collect($draft)
            ->map(fn (mixed $value): bool => (bool) $value)
            ->all();
    }

    protected function persistPendingSkippedSets(TrainingProgramSlotExercise $exercise): void
    {
        $draft = $this->pendingSkippedSets[$exercise->id] ?? [];

        if ($draft === []) {
            return;
        }

        foreach ($exercise->sets as $set) {
            $shouldBeSkipped = (bool) ($draft[$set->id] ?? false);
            $isSkipped = (string) ($set->status->value ?? $set->status) === TrainingProgramSlotSetStatusEnum::Skipped->value;

            if ($shouldBeSkipped && ! $isSkipped) {
                app(TrainingSessionProgressService::class)->markSetSkipped($set);
            }

            if (! $shouldBeSkipped && $isSkipped) {
                app(TrainingSessionProgressService::class)->markSetPending($set);
            }
        }
    }

    protected function pendingSkipDraftSkipsEntireExercise(TrainingProgramSlotExercise $exercise): bool
    {
        $draft = $this->pendingSkippedSets[$exercise->id] ?? [];

        if ($draft === []) {
            return false;
        }

        foreach ($exercise->sets as $set) {
            if (! ($draft[$set->id] ?? false)) {
                return false;
            }
        }

        return true;
    }

    protected function castEmptyStringsToNull(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->castEmptyStringsToNull($value);
                continue;
            }

            if ($value === '') {
                $data[$key] = null;
            }
        }

        return $data;
    }

    protected function sessionUserId(): int
    {
        return $this->previewMode
            ? (int) ($this->previewUserId ?? 0)
            : (int) auth()->id();
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
            'canRecordSession' => $this->canRecordSession,
            'athleteEditsEnabled' => $this->athleteEditsEnabled,
            'previewMode' => $this->previewMode,
        ])->layout('components.layouts.athlete', ['title' => $this->trainingProgram->program->name]);
    }
}
