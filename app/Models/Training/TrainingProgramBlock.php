<?php

namespace App\Models\Training;

use App\Data\Training\Blocks\BlockConfig;
use App\Models\Concerns\HasOwner;
use App\Models\Tag;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Coda\Cms\Models\Concerns\HasQueryBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingProgramBlock extends Model
{
    use HasFactory;
    use HasOwner;
    use HasQueryBuilder;
    use SoftDeletes;

    protected $table = 'training_program_blocks';

    protected $fillable = [
        'owner_id',
        'group_id',
        'user_id',
        'category_id',
        'type',
        'start',
        'end',
        'note',
        'color',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'type' => TrainingProgramBlockTypeEnum::class,
            'start' => 'date',
            'end' => 'date',
            'config' => BlockConfig::class,
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(Tag::class, 'category_id');
    }
}
