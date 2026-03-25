<?php

namespace App\Livewire\Training;

use App\Data\Training\Calendar\CalendarModeSettingsData;
use App\Data\Training\Calendar\CalendarSettingsData;
use App\Livewire\Training\Concerns\WithCalendarPlan;
use App\Models\Training\TrainingProgram;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Training\CalendarDateService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.admin')]
#[Title('ARS - Athlete Training // Calendar')]
class CalendarIndex extends Component
{
    use WithCalendarPlan;

    #[Url]
    public string $period = 'month';

    #[Url]
    public string $date = '';

    #[Url(except: '')]
    public string $start = '';

    #[Url(except: '')]
    public string $end = '';

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

        $hasUrlOverride = request()->hasAny(['period', 'date', 'start', 'end']);

        if (! $hasUrlOverride) {
            $stored = $this->loadPersistedCalendarSettings();
            if ($stored) {
                $this->applyCalendarSettings($stored);

                return;
            }
        }

        if ($this->date === '') {
            $this->date = Carbon::now()->startOfMonth()->format('Y-m-d');
        }

        $this->calendarSettings = new CalendarSettingsData(
            period: $this->period,
            date: $this->date,
            start: $this->start ?: null,
            end: $this->end ?: null,
        );
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
            $this->days,
            $this->weeks,
            $this->months,
            $this->title,
        );
    }

    public function openCalendarRange(): void
    {
        $this->dispatch('open-calendar-range', data: $this->calendarSettings->toArray());
    }

    #[On('calendar-range.submitted')]
    public function onCalendarRangeSubmitted(array $data): void
    {
        $this->calendarSettings = CalendarSettingsData::from($data);

        $this->period = $this->calendarSettings->period;
        $this->date = $this->calendarSettings->date ?? $this->date;
        $this->start = $this->calendarSettings->start ?? '';
        $this->end = $this->calendarSettings->end ?? '';

        $this->persistCalendarSettings();

        unset($this->days, $this->weeks, $this->months, $this->title);

        $this->dispatch('calendar-range-changed',
            settings: $this->calendarSettings->toArray(),
            weekStartsOn: $this->weekStartsOn,
            weekEndsOn: $this->weekEndsOn,
        );
    }

    private function persistCalendarSettings(): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $user->config->set("calendar.settings.{$this->view}", [
            'period' => $this->period,
            'date' => $this->date,
            'start' => $this->start ?: null,
            'end' => $this->end ?: null,
        ]);
        $user->save();
    }

    private function loadPersistedCalendarSettings(): ?CalendarModeSettingsData
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        $stored = $user->config->get("calendar.settings.{$this->view}");

        if (! $stored) {
            return null;
        }

        return CalendarModeSettingsData::from($stored);
    }

    private function applyCalendarSettings(CalendarModeSettingsData $settings): void
    {
        $this->period = $settings->period;
        $this->date = $settings->date ?? Carbon::now()->startOfMonth()->format('Y-m-d');
        $this->start = $settings->start ?? '';
        $this->end = $settings->end ?? '';

        $this->calendarSettings = new CalendarSettingsData(
            period: $this->period,
            date: $this->date,
            start: $this->start ?: null,
            end: $this->end ?: null,
        );
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
            unset($this->selectionName, $this->programs, $this->groupedPrograms);

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

        unset($this->selectionName, $this->programs, $this->groupedPrograms);

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
        unset($this->hasOverviewGroups);
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
    public function title(): string
    {
        return app(CalendarDateService::class)->formatTitle($this->calendarSettings, $this->weekStartsOn, $this->weekEndsOn);
    }

    #[Computed]
    public function hasOverviewGroups(): bool
    {
        $query = UserGroup::query();

        if ($this->groupFilter === 'mine') {
            $query->where('owner_id', auth()->id());
        }

        return $query->exists();
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
            $this->programs,
            $this->groupedPrograms,
            $this->hasOverviewGroups,
        );
    }

    public function render()
    {
        if ($this->view === 'plan' && $this->planProgramName === '' && $this->planProgram !== '') {
            $this->syncPlanProgramName();
        }

        return view('livewire.training.calendar-index');
    }
}
