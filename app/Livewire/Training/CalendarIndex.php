<?php

namespace App\Livewire\Training;

use App\Data\Training\Calendar\CalendarModeSettingsData;
use App\Data\Training\Calendar\CalendarSettingsData;
use App\Data\Training\CategoryData;
use App\Livewire\Training\Concerns\WithCalendarPlan;
use App\Models\Exercise\Exercise;
use App\Models\Exercise\ExerciseProgram;
use App\Models\Exercise\ExerciseProgramTypeEnum;
use App\Models\Tag;
use App\Models\Training\TrainingProgram;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Training\CalendarDateService;
use Carbon\Carbon;
use Coda\Cms\Livewire\CmsPage;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class CalendarIndex extends Component
{
    use WithCalendarPlan;

    #[Url(except: '')]
    public string $start = '';

    #[Url(except: '')]
    public string $end = '';

    #[Url(except: '')]
    public string $preset = '';

    public array $range = [];

    #[Url(except: 'overview', history: true)]
    public string $view = 'overview';

    #[Url(except: '')]
    public string $group = '';

    #[Url(except: '')]
    public string $user = '';

    #[Url(except: '')]
    public string $planCategory = '';

    #[Url(except: 'ungrouped')]
    public string $planBlock = 'ungrouped';

    #[Url(except: '')]
    public string $planProgram = '';

    #[Url(except: 'mine')]
    public string $groupFilter = 'mine';

    public string $planProgramName = '';

    public string $addContentSearch = '';

    public string $addContentTab = 'program';

    public CalendarSettingsData $calendarSettings;

    public int $weekStartsOn;

    public int $weekEndsOn;

    public function mount(): void
    {
        $this->weekStartsOn = (int) config('training.week_starts_on', Carbon::MONDAY);
        $this->weekEndsOn = ($this->weekStartsOn + 6) % 7;

        if ($this->groupFilter === 'mine' && ! request()->has('groupFilter')) {
            $ownsGroups = UserGroup::where('owner_id', auth()->id())->exists();
            if (! $ownsGroups) {
                $this->groupFilter = 'all';
            }
        }

        $hasUrlOverride = request()->hasAny(['start', 'end', 'preset']);

        if (! $hasUrlOverride) {
            $stored = $this->loadPersistedCalendarSettings();
            if ($stored) {
                $this->applyCalendarSettings($stored);

                return;
            }
        }

        $this->calendarSettings = new CalendarSettingsData(
            start: $this->start ?: null,
            end: $this->end ?: null,
            preset: CalendarDateService::normalizePreset($this->preset),
        );

        if (CalendarDateService::isConcretePreset($this->calendarSettings->preset)) {
            [$rangeStart, $rangeEnd] = app(CalendarDateService::class)->presetRange($this->calendarSettings->preset);
            $this->preset = $this->calendarSettings->preset;
            $this->start = '';
            $this->end = '';
            $this->calendarSettings = new CalendarSettingsData(
                start: $rangeStart->format('Y-m-d'),
                end: $rangeEnd->format('Y-m-d'),
                preset: $this->calendarSettings->preset,
            );
        } elseif ($this->calendarSettings->preset === CalendarDateService::PRESET_CUSTOM || $this->calendarSettings->start || $this->calendarSettings->end) {
            $normalized = app(CalendarDateService::class)->normalizeRange(
                $this->calendarSettings->start,
                $this->calendarSettings->end,
            );

            $this->start = $normalized['start'];
            $this->end = $normalized['end'];
            $this->preset = CalendarDateService::PRESET_CUSTOM;
            $this->calendarSettings = new CalendarSettingsData(start: $this->start, end: $this->end, preset: $this->preset);
        } else {
            $this->preset = CalendarDateService::PRESET_NEXT_3_MONTHS;
            [$rangeStart, $rangeEnd] = app(CalendarDateService::class)->presetRange($this->preset);
            $this->start = '';
            $this->end = '';
            $this->calendarSettings = new CalendarSettingsData(
                start: $rangeStart->format('Y-m-d'),
                end: $rangeEnd->format('Y-m-d'),
                preset: $this->preset,
            );
        }

        $this->syncRangeFromState();
    }

    public function updatedView(): void
    {
        if ($this->view !== 'plan') {
            $this->planCategory = '';
            $this->planBlock = 'ungrouped';
            $this->planProgram = '';
            $this->planProgramName = '';

            $stored = $this->loadPersistedCalendarSettings();
            if ($stored) {
                $this->applyCalendarSettings($stored);
                unset($this->days, $this->weeks, $this->months, $this->title);
            }
        }

        if ($this->view === 'plan' && $this->planBlock === 'ungrouped') {
            if ($this->planCategory === '' && $this->hasSelection()) {
                $_ = $this->planCategoryOptions;
            }
            if ($this->planCategory !== '') {
                $this->selectOverlappingBlock();
            }
        }

        if ($this->view === 'plan') {
            $this->syncPlanProgramName();
        }

        unset(
            $this->programs,
            $this->groupedPrograms,
            $this->hasOverviewGroups,
            $this->selectionName,
            $this->selectionGroupName,
            $this->selectionType,
            $this->days,
            $this->weeks,
            $this->months,
            $this->title,
        );
    }

    public function updatedRange(mixed $value): void
    {
        if (! is_array($value)) {
            return;
        }

        $this->applyCalendarRange($value, $value['preset'] ?? null);
    }

    public function updatedRangePreset(mixed $value): void
    {
        $this->applyCalendarRange($this->range, is_string($value) ? $value : null);
    }

    public function updatedRangeStart(): void
    {
        $this->applyCalendarRange($this->range, $this->range['preset'] ?? null);
    }

    public function updatedRangeEnd(): void
    {
        $this->applyCalendarRange($this->range, $this->range['preset'] ?? null);
    }

    #[On('calendar-range.submitted')]
    public function onCalendarRangeSubmitted(array $data): void
    {
        $this->applyCalendarRange($data);
    }

    private function persistCalendarSettings(): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $user->config->set('calendar.settings', [
            'preset' => $this->preset ?: null,
            'start' => $this->preset === CalendarDateService::PRESET_CUSTOM ? ($this->start ?: null) : null,
            'end' => $this->preset === CalendarDateService::PRESET_CUSTOM ? ($this->end ?: null) : null,
        ]);
        $user->save();
    }

    private function loadPersistedCalendarSettings(): ?CalendarModeSettingsData
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        $stored = $user->config->get('calendar.settings');

        if (! $stored) {
            return null;
        }

        return CalendarModeSettingsData::from($stored);
    }

    private function applyCalendarSettings(CalendarModeSettingsData $settings): void
    {
        $this->preset = CalendarDateService::normalizePreset(data_get($settings->toArray(), 'preset')) ?? '';
        $this->start = $settings->start ?? '';
        $this->end = $settings->end ?? '';

        if (CalendarDateService::isConcretePreset($this->preset)) {
            [$rangeStart, $rangeEnd] = app(CalendarDateService::class)->presetRange($this->preset);
            $this->start = '';
            $this->end = '';
            $resolvedStart = $rangeStart->format('Y-m-d');
            $resolvedEnd = $rangeEnd->format('Y-m-d');
        } elseif ($this->preset === CalendarDateService::PRESET_CUSTOM || $this->start !== '' || $this->end !== '') {
            $normalized = app(CalendarDateService::class)->normalizeRange($this->start, $this->end);
            $this->start = $normalized['start'];
            $this->end = $normalized['end'];
            $this->preset = CalendarDateService::PRESET_CUSTOM;
            $resolvedStart = $this->start;
            $resolvedEnd = $this->end;
        } else {
            $this->preset = CalendarDateService::PRESET_NEXT_3_MONTHS;
            [$rangeStart, $rangeEnd] = app(CalendarDateService::class)->presetRange($this->preset);
            $this->start = '';
            $this->end = '';
            $resolvedStart = $rangeStart->format('Y-m-d');
            $resolvedEnd = $rangeEnd->format('Y-m-d');
        }

        $this->calendarSettings = new CalendarSettingsData(
            start: $resolvedStart ?? ($this->start ?: null),
            end: $resolvedEnd ?? ($this->end ?: null),
            preset: $this->preset ?: null,
        );

        $this->syncRangeFromState();
    }

    #[On('sidebar-selection-changed')]
    public function onSidebarSelectionChanged(array $selected): void
    {
        $previousGroup = $this->group;
        $previousView = $this->view;

        $this->group = '';
        $this->user = '';

        if (count($selected) === 0) {
            $this->view = 'overview';
            $this->planCategory = '';
            $this->planBlock = 'ungrouped';
            $this->planProgram = '';
            unset($this->selectionName, $this->selectionGroupName, $this->selectionType, $this->programs, $this->groupedPrograms);

            return;
        }

        $first = $selected[0];
        $this->group = (string) $first['group'];

        if (isset($first['user'])) {
            $this->user = (string) $first['user'];
        }

        $sameGroup = $this->group === $previousGroup;

        if (! $sameGroup) {
            $this->view = 'overview';
            $this->planCategory = '';
            $this->planBlock = 'ungrouped';
            $this->planProgram = '';
            $this->planProgramName = '';
        } elseif ($this->view === 'plan' && $this->planCategory !== '') {
            $this->selectOverlappingBlock();
        }

        unset($this->selectionName, $this->selectionGroupName, $this->selectionType, $this->programs, $this->groupedPrograms);

        if ($this->group !== '') {
            $this->dispatch('calendar-selection-changed',
                groupId: (int) $this->group,
                userId: $this->user !== '' ? (int) $this->user : null,
            );
        }
    }

    #[On('group-filter-changed')]
    public function onGroupFilterChanged(string $filter): void
    {
        $this->groupFilter = $filter;
        $this->syncSelectionForGroupFilter();
    }

    #[On('coach-settings-saved')]
    public function onCoachSettingsSaved(): void
    {
        unset($this->showSidebar, $this->groupOptions, $this->athleteOptions);
    }

    #[Computed]
    public function selectionName(): ?string
    {
        if ($this->user !== '') {
            return User::find((int) $this->user)?->name;
        }

        if ($this->group !== '') {
            return UserGroup::find((int) $this->group)?->name;
        }

        return null;
    }

    #[Computed]
    public function selectionGroupName(): ?string
    {
        if ($this->user !== '' && $this->group !== '') {
            return UserGroup::find((int) $this->group)?->name;
        }

        return null;
    }

    #[Computed]
    public function selectionType(): ?string
    {
        if ($this->user !== '') {
            return 'athlete';
        }

        if ($this->group !== '') {
            return 'group';
        }

        return null;
    }

    public function hasSelection(): bool
    {
        return $this->group !== '' || $this->user !== '';
    }

    #[Computed]
    public function hasGroupAthletes(): bool
    {
        if ($this->user !== '') {
            return true;
        }

        if ($this->group === '') {
            return false;
        }

        return UserGroup::find((int) $this->group)?->members()->exists() ?? false;
    }

    #[Computed]
    public function exerciseCategoryLegend(): Collection
    {
        return Tag::query()
            ->forScope('exercise_category')
            ->whereNull('parent_id')
            ->whereNotNull('color')
            ->orderBy('name')
            ->get()
            ->map(fn (Tag $tag) => CategoryData::fromTag($tag));
    }

    #[Computed]
    public function hasOverviewGroups(): bool
    {
        $query = UserGroup::query();

        if ($this->groupFilter === 'mine' && auth()->check()) {
            $query->where('owner_id', auth()->id());
        }

        return $query->exists();
    }

    #[Computed]
    public function showSidebar(): bool
    {
        return auth()->user()?->config->get('settings.calendar_sidebar.enabled', true) ?? true;
    }

    #[Computed]
    public function groupOptions(): Collection
    {
        $query = UserGroup::query();

        if ($this->groupFilter === 'mine' && auth()->check()) {
            $query->where('owner_id', auth()->id());
        }

        return $query
            ->orderBy('name')
            ->get(['id', 'name'])
            ->pluck('name', 'id');
    }

    #[Computed]
    public function athleteOptions(): Collection
    {
        if ($this->group === '') {
            return collect();
        }

        return UserGroup::find((int) $this->group)
            ?->members()
            ->orderBy('forename')
            ->get(['users.id', 'forename', 'surname'])
            ->pluck('name', 'id') ?? collect();
    }

    public function updatedGroup(string $value): void
    {
        $previousGroup = $this->group;
        $this->user = '';

        unset(
            $this->athleteOptions,
            $this->selectionName,
            $this->selectionGroupName,
            $this->selectionType,
            $this->programs,
            $this->groupedPrograms,
            $this->hasGroupAthletes,
        );

        if ($this->group === '') {
            $this->view = 'overview';
            $this->planCategory = '';
            $this->planBlock = 'ungrouped';
            $this->planProgram = '';

            return;
        }

        if ($this->group !== $previousGroup) {
            $this->view = 'overview';
            $this->planCategory = '';
            $this->planBlock = 'ungrouped';
            $this->planProgram = '';
            $this->planProgramName = '';
        }

        $this->dispatch('calendar-selection-changed',
            groupId: (int) $this->group,
            userId: null,
        );
    }

    public function updatedUser(): void
    {
        unset(
            $this->selectionName,
            $this->selectionGroupName,
            $this->selectionType,
            $this->programs,
            $this->groupedPrograms,
        );

        if ($this->group !== '') {
            $this->dispatch('calendar-selection-changed',
                groupId: (int) $this->group,
                userId: $this->user !== '' ? (int) $this->user : null,
            );
        }
    }

    public function updatedGroupFilter(): void
    {
        $this->syncSelectionForGroupFilter();
    }

    #[Computed]
    public function groupedPrograms(): Collection
    {
        return $this->programs->groupBy(
            fn (TrainingProgram $entry) => $entry->program->exercise_category_id ?? 0
        )->map(function (Collection $entries, int $categoryId) {
            return [
                'category' => $categoryId > 0 ? $entries->first()->program->exerciseCategory : null,
                'entries' => $entries,
            ];
        });
    }

    #[Computed]
    public function programs(): Collection
    {
        if (! $this->hasSelection()) {
            return collect();
        }

        return TrainingProgram::with([
            'program.exerciseCategory',
            'program.exercises',
        ])
            ->where('group_id', (int) $this->group)
            ->orderBy('sort')
            ->get();
    }

    #[Computed]
    public function days(): array
    {
        [$start, $end] = $this->dateRange();

        return app(CalendarDateService::class)->buildDays($start, $end);
    }

    #[Computed]
    public function weeks(): array
    {
        [$start, $end] = $this->dateRange();

        return app(CalendarDateService::class)->buildWeeks($start, $end);
    }

    #[Computed]
    public function months(): array
    {
        [$start, $end] = $this->dateRange();

        return app(CalendarDateService::class)->buildMonths($start, $end);
    }

    /** @return array{Carbon, Carbon} */
    protected function dateRange(): array
    {
        return app(CalendarDateService::class)->dateRange($this->calendarSettings, $this->weekStartsOn, $this->weekEndsOn);
    }

    protected function applyCalendarRange(array $data, ?string $preset = null): void
    {
        $normalizedPreset = CalendarDateService::normalizePreset($preset ?? ($data['preset'] ?? null));

        if (CalendarDateService::isConcretePreset($normalizedPreset)) {
            [$rangeStart, $rangeEnd] = app(CalendarDateService::class)->presetRange($normalizedPreset);

            $normalized = [
                'start' => $rangeStart->format('Y-m-d'),
                'end' => $rangeEnd->format('Y-m-d'),
            ];

            $this->start = '';
            $this->end = '';
            $this->range = [...$normalized, 'preset' => $normalizedPreset];
            $this->preset = $normalizedPreset;
            $this->calendarSettings = new CalendarSettingsData(
                start: $normalized['start'],
                end: $normalized['end'],
                preset: $this->preset,
            );

            $this->persistCalendarSettings();

            unset($this->days, $this->weeks, $this->months, $this->title);

            $this->dispatch('calendar-range-changed',
                settings: $this->calendarSettings->toArray(),
                weekStartsOn: $this->weekStartsOn,
                weekEndsOn: $this->weekEndsOn,
            );

            return;
        }

        if (empty($data['start']) || empty($data['end'])) {
            $this->range = [
                'start' => $data['start'] ?? null,
                'end' => $data['end'] ?? null,
                'preset' => $data['preset'] ?? null,
            ];

            return;
        }

        $normalized = app(CalendarDateService::class)->normalizeRange(
            $data['start'] ?? null,
            $data['end'] ?? null,
        );

        $this->start = $normalized['start'];
        $this->end = $normalized['end'];
        $this->range = [...$normalized, 'preset' => CalendarDateService::PRESET_CUSTOM];
        $this->preset = CalendarDateService::PRESET_CUSTOM;
        $this->calendarSettings = new CalendarSettingsData(
            start: $this->start,
            end: $this->end,
            preset: $this->preset,
        );

        $this->persistCalendarSettings();

        unset($this->days, $this->weeks, $this->months, $this->title);

        $this->dispatch('calendar-range-changed',
            settings: $this->calendarSettings->toArray(),
            weekStartsOn: $this->weekStartsOn,
            weekEndsOn: $this->weekEndsOn,
        );
    }

    protected function syncRangeFromState(): void
    {
        $this->range = [
            'start' => $this->calendarSettings->start,
            'end' => $this->calendarSettings->end,
            'preset' => $this->preset ?: null,
        ];
    }

    protected function syncSelectionForGroupFilter(): void
    {
        unset($this->groupOptions, $this->hasOverviewGroups);

        if ($this->group === '') {
            return;
        }

        if ($this->groupOptions->has((int) $this->group)) {
            return;
        }

        $this->group = '';
        $this->user = '';
        $this->view = 'overview';
        $this->planCategory = '';
        $this->planBlock = 'ungrouped';
        $this->planProgram = '';
        $this->planProgramName = '';

        unset(
            $this->athleteOptions,
            $this->selectionName,
            $this->selectionGroupName,
            $this->selectionType,
            $this->programs,
            $this->groupedPrograms,
            $this->hasGroupAthletes,
        );
    }

    #[On('overview-selection')]
    public function onOverviewSelection(array $selected): void
    {
        $sel = $selected[0] ?? null;
        if (! $sel) {
            return;
        }

        $this->group = (string) $sel['group'];
        $this->user = isset($sel['user']) ? (string) $sel['user'] : '';

        unset(
            $this->selectionName,
            $this->selectionGroupName,
            $this->selectionType,
            $this->programs,
            $this->groupedPrograms,
            $this->hasOverviewGroups,
        );
    }

    public function openAddBlock(): void
    {
        $categoryOptions = $this->groupedPrograms
            ->filter(fn (array $group, int $categoryId) => $categoryId > 0 && $group['category'] !== null)
            ->mapWithKeys(fn (array $group, int $categoryId) => [
                $categoryId => [
                    'name' => $group['category']->name,
                    'slug' => $group['category']->slug,
                ],
            ])
            ->all();

        $this->dispatch('open-block', data: [
            'groupId' => (int) $this->group,
            'userId' => $this->user !== '' ? (int) $this->user : null,
            'categoryOptions' => $categoryOptions,
        ]);
    }

    #[On('trigger-add-content')]
    public function openAddProgram(): void
    {
        $this->addContentSearch = '';
        $this->addContentTab = 'program';
        Flux::modal('add-content')->show();
    }

    public function updatedAddContentTab(): void
    {
        $this->addContentSearch = '';
        unset($this->addContentOptions);
    }

    public function updatedAddContentSearch(): void
    {
        unset($this->addContentOptions);
    }

    #[Computed]
    public function addContentOptions(): Collection
    {
        $search = trim($this->addContentSearch);

        return match ($this->addContentTab) {
            'program' => ExerciseProgram::query()
                ->with('exerciseCategory:id,name,color')
                ->where('type', ExerciseProgramTypeEnum::Program)
                ->whereNull('parent_id')
                ->whereNull('parent_type')
                ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->limit(20)
                ->get(['id', 'name', 'exercise_category_id']),
            'exercise' => Exercise::query()
                ->with('category.rootAncestorOrSelf')
                ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->limit(20)
                ->get(['id', 'name', 'category_id']),
            default => collect(),
        };
    }

    public function addFromProgram(int $programId): void
    {
        $program = ExerciseProgram::findOrFail($programId);

        TrainingProgram::importProgram($program, (int) $this->group);

        unset($this->programs, $this->groupedPrograms);
        Flux::modal('add-content')->close();

        $this->dispatch('programs-changed');
    }

    public function addFromExercise(int $exerciseId): void
    {
        $exercise = Exercise::findOrFail($exerciseId);

        TrainingProgram::importExercise($exercise, (int) $this->group, categoryId: $exercise->category_id);

        unset($this->programs, $this->groupedPrograms);
        Flux::modal('add-content')->close();

        $this->dispatch('programs-changed');
    }

    public function render()
    {
        if ($this->view === 'plan' && $this->planProgramName === '' && $this->planProgram !== '') {
            $this->syncPlanProgramName();
        }

        return view('livewire.training.calendar-index')
            ->layout(CmsPage::layout())
            ->title(CmsPage::buildTitle(__('Calendar')));
    }
}
