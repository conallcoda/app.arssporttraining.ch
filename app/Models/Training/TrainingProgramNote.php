<?php

namespace App\Models\Training;

use App\Models\Concerns\HasOwner;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Coda\Cms\Models\Concerns\HasQueryBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingProgramNote extends Model
{
    use HasFactory;
    use HasOwner;
    use HasQueryBuilder;
    use SoftDeletes;

    protected $table = 'training_program_notes';

    protected $fillable = [
        'owner_id',
        'group_id',
        'user_id',
        'type',
        'start',
        'end',
        'note',
        'color',
    ];

    protected function casts(): array
    {
        return [
            'type' => TrainingProgramNoteTypeEnum::class,
            'start' => 'date',
            'end' => 'date',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(UserGroup::class, 'group_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
