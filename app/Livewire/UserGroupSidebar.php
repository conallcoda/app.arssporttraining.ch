<?php

namespace App\Livewire;

use App\Models\Users\UserGroup;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class UserGroupSidebar extends Component
{
    public string $mode = 'single-athlete';

    public ?string $navigateEvent = null;

    /** @var list<array{group: int, user?: int}> */
    public array $selected = [];

    public string $search = '';

    public string $groupFilter = 'mine';

    public bool $showGroupFilter = false;

    public function mount(string $mode = 'single-athlete', ?string $navigateEvent = null, ?int $initialGroup = null, ?int $initialUser = null, bool $showGroupFilter = false, ?string $initialGroupFilter = null): void
    {
        $this->mode = $mode;
        $this->navigateEvent = $navigateEvent;
        $this->showGroupFilter = $showGroupFilter;

        if ($initialGroup) {
            $item = ['group' => $initialGroup];

            if ($initialUser) {
                $item['user'] = $initialUser;
            }

            $this->selected = [$item];
        }

        if ($this->showGroupFilter) {
            if ($initialGroupFilter !== null) {
                $this->groupFilter = $initialGroupFilter;
            } else {
                $hasOwned = UserGroup::where('owner_id', auth()->id())->exists();
                $this->groupFilter = $hasOwned ? 'mine' : 'all';
            }
        }
    }

    public function updatedGroupFilter(): void
    {
        unset($this->groups);
        $this->dispatch('group-filter-changed', filter: $this->groupFilter);
    }

    /** @return Collection<int, UserGroup> */
    #[Computed]
    public function groups(): Collection
    {
        $query = UserGroup::with('members');

        if ($this->showGroupFilter && $this->groupFilter === 'mine') {
            $query->where('owner_id', auth()->id());
        }

        if ($this->search !== '') {
            $search = $this->search;

            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('members', fn ($m) => $m->where('forename', 'like', "%{$search}%")
                        ->orWhere('surname', 'like', "%{$search}%"));
            });
        }

        return $query->get();
    }

    public function hasMatchingMember(UserGroup $group): bool
    {
        if ($this->search === '') {
            return false;
        }

        return $group->members->contains(
            fn ($member): bool => str_contains(mb_strtolower($member->name), mb_strtolower($this->search))
        );
    }

    public function isMemberMatch($member): bool
    {
        if ($this->search === '') {
            return false;
        }

        return str_contains(mb_strtolower($member->name), mb_strtolower($this->search));
    }

    public function selectUser(int $groupId, int $userId): void
    {
        if ($this->mode === 'single-group') {
            return;
        }

        $item = ['group' => $groupId, 'user' => $userId];

        if ($this->mode === 'single-athlete') {
            $this->selected = $this->isSelected($groupId, $userId) ? [] : [$item];
        } else {
            if ($this->isSelected($groupId, $userId)) {
                $this->selected = array_values(array_filter(
                    $this->selected,
                    fn (array $s): bool => ! ($s['group'] === $groupId && ($s['user'] ?? null) === $userId)
                ));
            } else {
                $this->selected[] = $item;
            }
        }

        $this->dispatchSelectionChanged();
    }

    public function selectGroup(int $groupId): void
    {
        $item = ['group' => $groupId];

        $this->selected = $this->isGroupSelected($groupId) ? [] : [$item];

        $this->dispatchSelectionChanged();
    }

    public function isSelected(int $groupId, ?int $userId = null): bool
    {
        foreach ($this->selected as $s) {
            if ($s['group'] === $groupId && ($s['user'] ?? null) === $userId) {
                return true;
            }
        }

        return false;
    }

    public function isGroupSelected(int $groupId): bool
    {
        foreach ($this->selected as $s) {
            if ($s['group'] === $groupId && ! isset($s['user'])) {
                return true;
            }
        }

        return false;
    }

    public function hasSelectionInGroup(int $groupId): bool
    {
        foreach ($this->selected as $s) {
            if ($s['group'] === $groupId) {
                return true;
            }
        }

        return false;
    }

    public function navigate(string $type, int $groupId, ?int $userId = null): void
    {
        if ($this->navigateEvent) {
            $this->dispatch($this->navigateEvent, type: $type, group: $groupId, user: $userId);
        }
    }

    #[On('overview-selection')]
    public function onOverviewSelection(array $selected): void
    {
        $this->selected = $selected;
    }

    protected function dispatchSelectionChanged(): void
    {
        $this->dispatch('sidebar-selection-changed', selected: $this->selected);
    }

    public function render()
    {
        return view('livewire.user-group-sidebar');
    }
}
