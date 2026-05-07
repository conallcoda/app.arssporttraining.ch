<?php

namespace App\Models\Training;

use App\Models\Users\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TrainingStateRevision extends Model
{
    use HasFactory;

    protected $table = 'training_state_revisions';

    protected $fillable = [
        'batch_id',
        'subject_type',
        'subject_id',
        'state_key',
        'before_value',
        'after_value',
        'before_payload',
        'after_payload',
        'changed_by',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'before_payload' => 'array',
            'after_payload' => 'array',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(TrainingRevisionBatch::class, 'batch_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
