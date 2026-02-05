<?php

namespace App\Data\Athlete;

use App\Cms\Data\AbstractData;
use App\Cms\Form\Concerns\InteractsWithForms;
use App\Cms\Form\Form;
use App\Cms\Models\Contracts\HasForms;
use App\Form\Fields\AthleteGroup\GroupName;
use App\Form\Fields\AthleteGroup\Members;
use App\Models\Users\UserGroup;

class AthleteGroupData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public function __construct(
        public ?int $id,
        public string $name,
        public array $members = [],
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
        );
    }

    public function persist(): void
    {
        $group = UserGroup::updateOrCreate(
            ['id' => $this->id],
            ['name' => $this->name]
        );

        $this->id = $group->id;

        $group->syncSortableRelation($group->members(), $this->members);
    }

    public static function getForm(): Form
    {
        return Form::make()
            ->withFields([
                GroupName::make('name'),
                Members::make('members')->withOptions(),
            ])
            ->fieldset('general', 'General', ['name', 'members']);
    }
}
