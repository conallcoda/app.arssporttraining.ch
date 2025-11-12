<?php

namespace App\Models\Training;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingWeek extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'training_block_id',
        'sequence',
    ];

    protected $casts = [
        'sequence' => 'integer',
    ];

    public function block(): BelongsTo
    {
        return $this->belongsTo(TrainingBlock::class, 'training_block_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class);
    }
}
