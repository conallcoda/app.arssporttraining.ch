<?php

namespace App\Form\Fields\AthleteGroup;

use App\Models\Users\User;
use App\Models\Users\UserTypeEnum;
use Coda\Cms\Display\DisplayFields\Text;
use Coda\FormKit\Fields\RelationshipSelector;

class Members extends RelationshipSelector
{
    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->label = 'Members';
        $this->placeholder = 'Search athletes';
        $this->sortable = true;
        $this->default = [];
        $this->selectButtonLabel = 'Select';
        $this->emptySelectionText = 'No athletes selected yet.';
        $this->columns([
            Text::make('name')->maxLength(null),
            Text::make('email')->maxLength(null),
        ]);
    }

    public function withOptions(): static
    {
        $this->optionLoader = fn () => User::query()
            ->where('type', UserTypeEnum::Athlete)
            ->orderBy('forename')
            ->orderBy('surname')
            ->get()
            ->mapWithKeys(fn ($user) => [$user->id => $user->name])
            ->all();

        $this->searchable(function (string $query, array $selectedIds, array $excludedIds, array $filters, array $items, array $sort): iterable {
            return User::query()
                ->where('type', UserTypeEnum::Athlete)
                ->when($query !== '', function ($q) use ($query) {
                    $q->where(function ($w) use ($query): void {
                        $w->where('forename', 'like', "%{$query}%")
                            ->orWhere('surname', 'like', "%{$query}%")
                            ->orWhereRaw("concat(forename, ' ', surname) like ?", ["%{$query}%"]);
                    });
                })
                ->orderBy('forename')
                ->orderBy('surname')
                ->limit(40)
                ->get();
        })->selectedRecordsUsing(function (array $selectedIds): iterable {
            $selectedIdInts = collect($selectedIds)
                ->filter(fn ($id) => $id !== null && $id !== '')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            if ($selectedIdInts === []) {
                return collect();
            }

            return User::query()
                ->where('type', UserTypeEnum::Athlete)
                ->whereKey($selectedIdInts)
                ->get()
                ->sortBy(fn (User $user) => array_search($user->id, $selectedIdInts, true))
                ->values();
        });

        return $this;
    }
}
