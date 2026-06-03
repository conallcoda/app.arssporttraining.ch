<?php

namespace App\Data\Athlete;

use App\Form\Fields\AthleteGroup\GroupName;
use App\Form\Fields\AthleteGroup\Members;
use App\Form\Fields\Owner;
use App\Models\Users\UserGroup;
use Carbon\Carbon;
use Coda\Cms\Data\AbstractData;
use Coda\Cms\Form\Fields\Tags;
use Coda\FormKit\Concerns\InteractsWithForms;
use Coda\FormKit\Contracts\HasForms;
use Coda\FormKit\Form;

class AthleteGroupData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public function __construct(
        public ?int $id,
        public string $name,
        public array $members = [],
        public array $internalTags = [],
        public ?int $owner_id = null,
        public ?string $ownerName = null,
        public ?string $ownerColor = null,
        public ?Carbon $updatedAt = null,
    ) {}

    public static function fromModel(UserGroup $group): self
    {
        $members = [];

        $members = $group->members->map(fn ($user) => [
            'id' => $user->id,
            'name' => $user->name,
            'sort' => $user->pivot->sort ?? 0,
        ])->all();

        return new self(
            id: $group->id,
            name: $group->name ?? '',
            members: $members,
            internalTags: $group->relationLoaded('internalTags') ? $group->internalTags->pluck('id')->all() : [],
            owner_id: $group->owner_id ?? 0,
            ownerName: $group->relationLoaded('owner') ? $group->owner?->name : null,
            ownerColor: $group->relationLoaded('owner') ? $group->owner?->color : null,
            updatedAt: $group->updated_at,
        );
    }

    public function persist(): void
    {
        $group = UserGroup::updateOrCreate(
            ['id' => $this->id],
            [
                'name' => $this->name,
                'owner_id' => $this->owner_id,
            ]
        );

        $this->id = $group->id;

        $group->syncSortableRelation($group->members(), $this->members);

        $group->tags()->sync($this->internalTags);
    }

    public static function getForm(): Form
    {
        return Form::make()
            ->fieldset('General', [
                Owner::make('owner_id')->withOptions()->allowNoOwner(),
                GroupName::make('name'),
                Members::make('members')->withOptions(),
                Tags::make('internalTags', 'athlete_group_internal')->label('Tags')->withOptions()->create(),
            ]);
    }
}
