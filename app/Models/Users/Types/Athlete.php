<?php

namespace App\Models\Users\Types;

use App\Data\Address;
use App\Models\Users\User;
use Parental\HasParent;
use Illuminate\Database\Eloquent\Model;

class Athlete extends User
{
    use HasParent;

    public function allowedGroupTypes(): array
    {
        return ['athlete'];
    }

    public static function getExtraConfig(?Model $model = null): array
    {
        return [
            'address' => Address::class,
        ];
    }
}
