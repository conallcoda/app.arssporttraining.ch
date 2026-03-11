<?php

namespace App\Livewire\Database;

use App\Data\Athlete\AthleteGroupData;
use App\Models\Tag;
use App\Models\Users\UserGroup;
use App\Support\OwnershipTabs;
use Coda\Cms\Display\DisplayFields\Ago;
use Coda\Cms\Display\DisplayFields\Badge;
use Coda\Cms\Display\DisplayFields\Id;
use Coda\Cms\Display\DisplayFields\Relationship;
use Coda\Cms\Display\DisplayFields\Text;
use Coda\Cms\Display\Table;
use Coda\Cms\Display\TableFilter;
use Coda\Cms\Form\Fields\Text as TextField;
use Coda\Cms\Livewire\AbstractModelList;
use Illuminate\Contracts\Database\Eloquent\Builder;

class AthleteGroupList extends AbstractModelList
{
    protected function urlPrefix(): string
    {
        return 'agl_';
    }

    protected function getDataClass(): string
    {
        return AthleteGroupData::class;
    }

    protected function getTabs(): array
    {
        return OwnershipTabs::make('Groups')->toArray();
    }

    protected function getDefaultTabKey(): ?string
    {
        return OwnershipTabs::make('Groups')->defaultTab($this->getBaseQuery());
    }

    protected function getBaseQuery(): Builder
    {
        return UserGroup::query()->with(['internalTags', 'owner']);
    }

    protected function getTable(): Table
    {
        $tagNames = Tag::query()
            ->forScope('athlete_group_internal')
            ->pluck('name', 'id');

        return Table::make()
            ->columns([
                Id::make(),
                Text::make('name')->label(__('Name'))->modal(),
                Badge::make('coach')
                    ->label(__('Coach'))
                    ->source(fn (AthleteGroupData $data) => [
                        [
                            'label' => $data->ownerName ?? __('Unassigned'),
                            'color' => $data->ownerColor,
                            'modalField' => 'owner_id',
                        ],
                    ]),
                Relationship::make('members')->label(__('Members'))->modal()->width('w-full'),
                Badge::make('internalTags')
                    ->label(__('Tags'))
                    ->source(fn (AthleteGroupData $data) => collect($data->internalTags)
                        ->map(fn (int $id) => ['label' => $tagNames[$id] ?? '?'])
                        ->all()
                    ),
                Ago::make('updatedAt')->label(__('Last Changed')),
            ])
            ->sortable(['id', 'name', 'updatedAt'])
            ->filters([
                TableFilter::callback('search', function (Builder $query, mixed $value): void {
                    $query->where(function (Builder $query) use ($value): void {
                        $query->where('name', 'like', '%'.$value.'%')
                            ->orWhereHas('members', function (Builder $query) use ($value): void {
                                $query->where('forename', 'like', '%'.$value.'%')
                                    ->orWhere('surname', 'like', '%'.$value.'%');
                            });
                    });
                })
                    ->field(
                        TextField::make('search')
                            ->label(__('Search'))
                            ->placeholder(__('Search groups...'))
                    ),
            ]);
    }
}
