<?php

namespace App\Models\Concerns;

use App\Models\Users\User;
use App\Models\Users\UserTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasOwner
{
    public static function bootHasOwner(): void
    {
        static::creating(function (Model $model) {
            if ($model->owner_id === 0) {
                $model->owner_id = null;

                return;
            }

            if (is_null($model->owner_id) && auth()->user()?->type !== UserTypeEnum::Coach) {
                $model->owner_id = auth()->id();
            }
        });

        static::updating(function (Model $model) {
            if ($model->owner_id === 0) {
                $model->owner_id = null;
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
