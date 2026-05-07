<?php

namespace App\Models\Training;

use App\Data\Training\Blocks\BlockConfig;
use App\Models\Tag;
use App\Models\Users\User;
use App\Models\Users\UserGroup;
use Coda\Cms\Models\Concerns\HasOwner;
use Coda\Cms\Models\Concerns\HasQueryBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'parent_id',
        'type',
        'start',
        'end',
        'note',
        'color',
        'config',
        'active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => TrainingProgramBlockTypeEnum::class,
            'start' => 'date',
            'end' => 'date',
            'config' => BlockConfig::class,
            'active' => 'boolean',
            'created_by' => 'integer',
            'updated_by' => 'integer',
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

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Tag::class, 'category_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function athleteOverride(int $userId): ?self
    {
        return $this->children()->where('user_id', $userId)->first();
    }
}
