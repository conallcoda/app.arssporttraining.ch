<?php

namespace App\Livewire\Training;

use App\Data\Training\Calendar\CalendarSettingsData;
use App\Models\Training\TrainingProgramSlot;
use App\Models\Users\UserGroup;
use App\Training\CalendarDateService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class CalendarOverviewGrid extends Component
{
    public string $groupFilter = 'mine';

    public CalendarSettingsData $calendarSettings;

    public int $weekStartsOn;

    public int $weekEndsOn;

    #[On('group-filter-changed')]
    public function onGroupFilterChanged(string $filter): void
    {
        $this->groupFilter = $filter;
        unset($this->overviewData);
    }

    #[On('calendar-range-changed')]
    public function onCalendarRangeChanged(array $settings, int $weekStartsOn, int $weekEndsOn): void
    {
        $this->calendarSettings = CalendarSettingsData::from($settings);
        $this->weekStartsOn = $weekStartsOn;
        $this->weekEndsOn = $weekEndsOn;

        unset($this->overviewData, $this->days, $this->weeks, $this->months);
    }

    #[Computed]
    public function overviewData(): array
    {
        [$start, $end] = $this->dateRange();

        $groupsQuery = UserGroup::with('members')->orderBy('name');

        if ($this->groupFilter === 'mine') {
            $groupsQuery->where('owner_id', auth()->id());
        }

        $groups = $groupsQuery->get();
        $groupIds = $groups->pluck('id');

        $colorAgg = TrainingProgramSlot::query()
            ->join('training_programs', 'training_program_slots.training_program_id', '=', 'training_programs.id')
            ->join('exercise_programs', 'training_programs.exercise_program_id', '=', 'exercise_programs.id')
            ->leftJoin('tags', 'exercise_programs.exercise_category_id', '=', 'tags.id')
            ->whereNull('training_programs.deleted_at')
            ->whereIn('training_programs.group_id', $groupIds)
            ->whereBetween('training_program_slots.datetime', [
                $start->copy()->startOfDay(),
                $end->copy()->endOfDay(),
            ])
            ->selectRaw('training_programs.group_id, DATE(training_program_slots.datetime) as slot_date, COALESCE(tags.color, \'_none\') as category_color, COUNT(*) as cnt')
            ->groupByRaw('training_programs.group_id, DATE(training_program_slots.datetime), COALESCE(tags.color, \'_none\')')
            ->get();

        $groupDateColors = [];
        $groupDates = [];

        foreach ($colorAgg as $row) {
            $groupDateColors[$row->group_id][$row->slot_date][$row->category_color] = $row->cnt;
            $groupDates[$row->group_id][$row->slot_date] = true;
        }

        $result = [];

        foreach ($groups as $group) {
            $gid = $group->id;

            $result[] = [
                'group' => $group,
                'dates' => $groupDates[$gid] ?? [],
                'dateColors' => $groupDateColors[$gid] ?? [],
                'members' => $group->members->map(fn ($m) => ['user' => $m])->all(),
            ];
        }

        return $result;
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

    public function selectFromOverview(int $groupId, ?int $userId = null): void
    {
        $selected = ['group' => $groupId];
        if ($userId !== null) {
            $selected['user'] = $userId;
        }

        $this->dispatch('overview-selection', selected: [$selected]);
    }

    /** @return array{\Carbon\Carbon, \Carbon\Carbon} */
    protected function dateRange(): array
    {
        return app(CalendarDateService::class)->dateRange($this->calendarSettings, $this->weekStartsOn, $this->weekEndsOn);
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.training.calendar-overview-grid');
    }
}
