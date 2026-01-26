<?php

namespace App\Livewire\Database;

use App\Data\AbstractData;
use App\Data\Form\FluxField;
use App\Models\Contracts\HasForms;
use App\Models\Users\UserGroup;

class AthleteGroupData extends AbstractData implements HasForms
{
    public function __construct(
        public ?int $id,
        public string $name,
    ) {}

    public static function fromUserGroup(UserGroup $group): self
    {
        return new self(
            id: $group->id,
            name: $group->name ?? ''
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
    }

    public static function example(int $id = 1, string $name = 'Group A'): self
    {
        return new self(
            id: $id,
            name: $name,
        );
    }

    public static function getFields(): array
    {
        return [
            FluxField::text('name')
                ->label('Name')
                ->placeholder('Group name')
                ->required()
                ->default('')
                ->rules('required|string|min:1'),
        ];
    }
}
