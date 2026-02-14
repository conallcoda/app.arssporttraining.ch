<?php

namespace App\Data\Athlete;

use App\Form\Fields\AthleteGroup\GroupName;
use App\Form\Fields\AthleteGroup\Members;
use App\Models\Users\UserGroup;
use Carbon\Carbon;
use Coda\Cms\Data\AbstractData;
use Coda\Cms\Form\Concerns\InteractsWithForms;
use Coda\Cms\Form\Form;
use Coda\Cms\Models\Contracts\HasForms;

class AthleteGroupData extends AbstractData implements HasForms
{
    use InteractsWithForms;

    public function __construct(
        public ?int $id,
        public string $name,
        public array $members = [],
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
            updatedAt: $group->updated_at,
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
            ->fieldset('General', [
                GroupName::make('name'),
                Members::make('members')->withOptions(),
            ]);
    }
}
