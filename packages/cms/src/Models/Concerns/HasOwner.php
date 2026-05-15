<?php

namespace Coda\Cms\Models\Concerns;

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

            if (is_null($model->owner_id)) {
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
        $userModel = config('cms.models.user');

        return $this->belongsTo($userModel, 'owner_id');
    }
}
