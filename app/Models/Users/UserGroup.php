<?php

namespace App\Models\Users;

use App\Models\Concerns\HasExtraData;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Parental\HasChildren;

class UserGroup extends Model
{
    use HasFactory, HasExtraData, HasChildren;

    protected $fillable = [
        'name',
        'type',
    ];

    protected $childTypes = [
        'athlete' => \App\Models\Users\Groups\AthleteGroup::class,
    ];

    public function getChildTypes(): array
    {
        return $this->childTypes;
    }

    public static function getExtraConfig(?Model $model = null): array
    {
        return [];
    }
}
