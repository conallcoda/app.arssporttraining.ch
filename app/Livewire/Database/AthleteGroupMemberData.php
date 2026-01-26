<?php

namespace App\Livewire\Database;

use App\Data\AbstractData;
use App\Models\Users\User;

class AthleteGroupMemberData extends AbstractData
{
    public function __construct(
        public int $id,
        public string $name,
        public int $sort,
    ) {}

    public static function fromUser(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            sort: $user->pivot->sort ?? 0,
        );
    }
}
