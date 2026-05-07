<?php

namespace App\Models\Training;

use App\Models\Users\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TrainingRevisionBatch extends Model
{
    use HasFactory;

    protected $table = 'training_revision_batches';

    protected $fillable = [
        'owner_type',
        'owner_id',
        'domain',
        'action',
        'changed_by',
        'source',
        'reason',
    ];

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function actualValueRevisions(): HasMany
    {
        return $this->hasMany(TrainingActualValueRevision::class, 'batch_id');
    }

    public function planValueRevisions(): HasMany
    {
        return $this->hasMany(TrainingPlanValueRevision::class, 'batch_id');
    }

    public function stateRevisions(): HasMany
    {
        return $this->hasMany(TrainingStateRevision::class, 'batch_id');
    }
}
