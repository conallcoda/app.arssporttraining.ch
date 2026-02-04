<?php

namespace App\Livewire\Database;

use App\Data\AbstractData;
use App\Form\Fields\Relationship;
use App\Form\Fields\Text;
use App\Models\Contracts\HasForms;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use App\Models\Users\UserTypeEnum;

class AthleteGroupData extends AbstractData implements HasForms
{
    public function __construct(
        public ?int $id,
        public string $name,
        public array $members = [],
    ) {}

    public static function fromUserGroup(UserGroup $group): self
    {
        $members = [];
        if ($group->relationLoaded('members')) {
            $members = $group->members->map(fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'sort' => $user->pivot->sort ?? 0,
            ])->all();
        }

        return new self(
            id: $group->id,
            name: $group->name ?? '',
            members: $members,
        );
    }

    public function persist(): void
    {
        if ($this->id === null) {
            $group = UserGroup::create([
                'name' => $this->name,
            ]);
            $this->id = $group->id;
        } else {
            $group = UserGroup::findOrFail($this->id);
            $group->name = $this->name;
            $group->save();
        }

        $syncData = collect($this->members)
            ->filter(fn($member) => ! empty($member['id']))
            ->mapWithKeys(fn($member, $index) => [
                $member['id'] => ['sort' => $member['sort'] ?? $index],
            ])
            ->all();

        $group->members()->sync($syncData);
    }

    public static function getFields(): array
    {
        $athleteOptions = User::where('type', UserTypeEnum::Athlete)
            ->orderBy('forename')
            ->orderBy('surname')
            ->get()
            ->mapWithKeys(fn($user) => [$user->id => $user->name])
            ->all();

        return [
            Text::make('name')
                ->label('Name')
                ->placeholder('Group name')
                ->required()
                ->default('')
                ->rules('required|string|min:1'),
            Relationship::make('members')
                ->label('Members')
                ->options($athleteOptions)
                ->placeholder('Select athlete')
                ->sortable()
                ->default([]),
        ];
    }
}
