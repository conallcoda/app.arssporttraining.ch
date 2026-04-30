<?php

namespace App\Livewire\Training;

use App\Data\Exercise\ExerciseConfig;
use App\Data\Exercise\Preview\ExercisePreviewBuilder;
use App\Data\Exercise\Preview\GridOverrides;
use App\Data\Exercise\Preview\OverrideManager;
use App\Data\Exercise\Preview\PreviewGrid;
use App\Data\Exercise\Preview\SessionGroupBuilder;
use App\Data\Exercise\Preview\SessionGroupingMode;
use App\Data\Exercise\Settings\SetsSetting;
use App\Data\Exercise\Settings\WeightProgressionSetting;
use App\Data\Exercise\Strategies\Sets\DeloadSetsStrategy;
use App\Models\Exercise\ExerciseProgram;
use Coda\Cms\Livewire\FormModal;
use Coda\FormKit\Form;
use Coda\FormKit\FormFieldsetGroup;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

class CalendarExerciseSettingsForm extends FormModal
{
    public ?int $contextExerciseId = null;

    public ?int $contextExerciseProgramId = null;

    public int $gridWeeks = 5;

    public int $sessionsPerWeek = 1;

    public array $weekLabels = [];

    public array $weekSessions = [];

    public bool $scheduled = false;

    public ?int $contextUserId = null;

    public function mount(
        string $name = 'calendar-exercise-settings',
        string $title = 'exercise-settings-default',
        ?string $formDataClass = null,
        string $submitLabel = 'save-default',
        string $cancelLabel = 'cancel-default',
        bool $flyout = true,
        string $maxWidth = 'max-w-lg',
        bool $showDelete = false,
        array $excludeFields = [],
    ): void {
        parent::mount(
            name: $name,
            title: $title === 'exercise-settings-default' ? __('Exercise Settings') : $title,
            formDataClass: $formDataClass,
            submitLabel: $submitLabel === 'save-default' ? __('Save') : $submitLabel,
            cancelLabel: $cancelLabel === 'cancel-default' ? __('Cancel') : $cancelLabel,
            flyout: $flyout,
            maxWidth: $maxWidth,
            showDelete: $showDelete,
            excludeFields: $excludeFields,
        );

        unset($this->fieldsets);
        $this->data = array_replace_recursive($this->buildDefaultsFromFieldsets(), $this->data);
        unset($this->fieldsets);
    }

    #[Computed]
    public function formConfig(): Form
    {
        $form = Form::make();
        ExerciseConfig::addFormFieldsets($form);

        return $form;
    }

    #[Computed]
    public function fieldsets(): array
    {
        $fieldsets = $this->formConfig->resolveFieldsets($this->data);

        foreach ($fieldsets as $item) {
            if ($item instanceof FormFieldsetGroup) {
                $item->label = null;
            }
        }

        return $fieldsets;
    }

    #[Computed]
    public function previewGrid(): PreviewGrid
    {
        $config = $this->withResolvedPreviewGrouping($this->data['config'] ?? []);
        $overrides = GridOverrides::fromConfig($config['overrides'] ?? []);

        $grid = ExercisePreviewBuilder::build(
            $config,
            $this->getMeasuredData(),
            $this->gridWeeks,
            $overrides,
            $this->sessionsPerWeek,
            explicitWeekSessionCounts: $this->resolvedWeekSessionCounts(),
        );

        return $this->applyExplicitWeekSessionCounts($grid);
    }

    #[Computed]
    public function effectiveExpandedWeeks(): array
    {
        $expanded = [];

        foreach (range(0, $this->previewGrid->weekCount - 1) as $week) {
            if ($this->weekHasSessionDivergence($this->previewGrid, $week)) {
                $expanded[] = $week;
            }
        }

        return $expanded;
    }

    public function getListeners(): array
    {
        return [];
    }

    #[On('open-calendar-exercise-settings')]
    public function openForExercise(array $data): void
    {
        $this->contextExerciseId = $data['exerciseId'] ?? null;
        $this->contextExerciseProgramId = $data['exerciseProgramId'] ?? null;
        $this->gridWeeks = $data['weeks'] ?? 5;
        $this->sessionsPerWeek = $data['sessionsPerWeek'] ?? 1;
        $this->weekLabels = $data['weekLabels'] ?? [];
        $this->weekSessions = $data['weekSessions'] ?? [];
        $this->scheduled = $data['scheduled'] ?? false;
        $this->contextUserId = $data['userId'] ?? null;

        $config = $data['config'] ?? [];
        $exerciseName = $data['exerciseName'] ?? __('Exercise');

        $formData = [
            'name' => $exerciseName,
            'config' => $config,
        ];

        $this->open($formData, $exerciseName);
    }

    public function open(array $data = [], ?string $title = null, ?string $focusField = null, ?int $focusIndex = null): void
    {
        parent::open($data, $title, $focusField, $focusIndex);

        unset($this->fieldsets, $this->previewGrid);
        $this->data = array_replace_recursive($this->buildDefaultsFromFieldsets(), $this->data);
        unset($this->fieldsets);
    }

    public function updatedDataConfig(): void
    {
        unset($this->previewGrid);
    }

    public function updatedDataConfigSettings(): void
    {
        unset($this->fieldsets, $this->previewGrid);
        $settings = $this->data['config']['settings'];
        $this->data = array_replace_recursive($this->buildDefaultsFromFieldsets(), $this->data);
        $this->data['config']['settings'] = $settings;
        $this->data['config']['overrides'] = OverrideManager::reset();
    }

    public function updateCellOverride(int $weekIndex, int $setIndex, string $field, mixed $value, int $session = 0, bool $applyToAll = false): void
    {
        $this->data['config']['overrides'] = OverrideManager::updateCellOverride(
            $this->data['config']['overrides'] ?? OverrideManager::reset(),
            $this->data['config'],
            $this->gridWeeks,
            $this->sessionsPerWeek,
            $weekIndex,
            $setIndex,
            $field,
            $value,
            $session,
            false,
            weekSessionCount: $this->sessionCountForWeek($weekIndex),
        );

        unset($this->previewGrid);
    }

    public function updateSessionOverride(int $weekIndex, int $session, string $field, mixed $value): void
    {
        $effectiveDefault = null;

        if ($field === 'sets') {
            $config = $this->withResolvedPreviewGrouping($this->data['config'] ?? []);
            $preview = $config['preview'] ?? [];
            $sessionCounts = $this->resolvedWeekSessionCounts();
            $strategy = new DeloadSetsStrategy(
                SetsSetting::from($config['sets'] ?? []),
                groupingMode: (string) ($preview['groupingMode'] ?? SessionGroupingMode::Week->value),
                groupSize: max(1, (int) ($preview['groupSize'] ?? 4)),
                sessionCounts: $sessionCounts,
            );
            $groupMap = SessionGroupBuilder::buildStrategyMap(
                $this->gridWeeks,
                $sessionCounts,
                (string) ($preview['groupingMode'] ?? SessionGroupingMode::Week->value),
                max(1, (int) ($preview['groupSize'] ?? 4)),
            );
            $effectiveDefault = $strategy->getSetsForGroup($groupMap['groupIndexByWeekSession'][$weekIndex][$session] ?? $weekIndex);
        }

        $this->data['config']['overrides'] = OverrideManager::updateSessionOverride(
            $this->data['config']['overrides'] ?? OverrideManager::reset(),
            $this->data['config'],
            $weekIndex,
            $session,
            $field,
            $value,
            $effectiveDefault,
        );

        unset($this->previewGrid);
    }

    public function resetOverrides(): void
    {
        $this->data['config']['overrides'] = OverrideManager::reset();
        unset($this->previewGrid);
    }

    public function submit(): void
    {
        $this->validate($this->buildValidationRulesFromFieldsets(), [
            'required' => __('This field is required.'),
        ]);

        Flux::modal($this->name)->close();

        $this->dispatch('calendar-exercise-settings.saved', data: [
            'config' => $this->data['config'],
            'exerciseId' => $this->contextExerciseId,
            'exerciseProgramId' => $this->contextExerciseProgramId,
            'userId' => $this->contextUserId,
        ]);
    }

    protected function getMeasuredData(): ?WeightProgressionSetting
    {
        $program = ExerciseProgram::findOrFail($this->contextExerciseProgramId);
        $target = $program->config->defaultTarget();

        if ($target === null) {
            return null;
        }

        return WeightProgressionSetting::from([
            'measuredReps' => $target->measuredReps,
            'measuredWeight' => $target->measuredWeight,
            'targetGoal' => $target->targetGoal ?? 10,
        ]);
    }

    public function render(): View
    {
        return view('livewire.training.calendar-exercise-settings-form');
    }

    protected function sessionCountForWeek(int $weekIndex): int
    {
        $explicitSessions = (int) ($this->weekSessions[$weekIndex] ?? 0);

        if ($explicitSessions > 0) {
            return max($explicitSessions, 1);
        }

        return max($this->sessionsPerWeek, 1);
    }

    protected function applyExplicitWeekSessionCounts(PreviewGrid $grid): PreviewGrid
    {
        foreach (range(0, $grid->weekCount - 1) as $week) {
            $grid->weekSessionCounts[$week] = $this->sessionCountForWeek($week);
        }

        return $grid;
    }

    protected function weekHasSessionDivergence(PreviewGrid $grid, int $week): bool
    {
        $sessionCount = $grid->weekSessionCounts[$week] ?? $this->sessionCountForWeek($week);

        if ($sessionCount <= 1) {
            return false;
        }

        foreach ($grid->rows as $row) {
            if ($row->lastSessionOnly) {
                continue;
            }

            foreach (array_keys($row->cells[$week] ?? []) as $set) {
                $baselineValue = $row->getCellValue($week, (int) $set, 0);
                $baselineOverride = $row->isCellOverriddenAt($week, (int) $set, 0);

                for ($session = 1; $session < $sessionCount; $session++) {
                    if ($row->getCellValue($week, (int) $set, $session) !== $baselineValue) {
                        return true;
                    }

                    if ($row->isCellOverriddenAt($week, (int) $set, $session) !== $baselineOverride) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /** @return array<int, int> */
    protected function resolvedWeekSessionCounts(): array
    {
        $counts = [];

        for ($week = 0; $week < $this->gridWeeks; $week++) {
            $counts[$week] = $this->sessionCountForWeek($week);
        }

        return $counts;
    }

    protected function withResolvedPreviewGrouping(array $config): array
    {
        $preview = $config['preview'] ?? [];
        $grouping = $this->resolveDefaultPreviewGrouping();
        $preview['groupingMode'] = $grouping['mode'];
        $preview['groupSize'] = $grouping['groupSize'];

        $config['preview'] = $preview;

        return $config;
    }

    /**
     * @return array{mode: string, groupSize: int}
     */
    protected function resolveDefaultPreviewGrouping(): array
    {
        $user = Auth::user();

        return [
            'mode' => (string) ($user?->config->get('settings.session_grouping.mode', SessionGroupingMode::Week->value) ?? SessionGroupingMode::Week->value),
            'groupSize' => max(1, (int) ($user?->config->get('settings.session_grouping.groupSize', 4) ?? 4)),
        ];
    }
}
