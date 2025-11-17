<?php

namespace App\Models\Metrics;

use App\Data\MetricTypes\MetricType as MetricTypeData;
use App\Models\Exercise\Exercise;
use App\Models\Users\Types\Athlete;
use Database\Factories\MetricTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;
use Spatie\SchemalessAttributes\Casts\SchemalessAttributes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class MetricType extends Model
{
    use HasFactory, SoftDeletes, HasSlug;

    protected static function newFactory(): MetricTypeFactory
    {
        return MetricTypeFactory::new();
    }

    protected $fillable = [
        'scope',
        'type',
        'label',
        'name',
        'config',
    ];

    public $casts = [
        'config' => SchemalessAttributes::class,
    ];

    public function scopeWithConfigAttributes(): Builder
    {
        return $this->config->modelScope();
    }

    public static function scopes(): array
    {
        return [
            Athlete::class,
            Exercise::class,
        ];
    }


    public static function normalizedScoeps()
    {
        $mapScope = function ($modelClass) {
            $normalized = Str::snake(class_basename($modelClass));
            return [$normalized => $modelClass];
        };
        return collect(self::scopes())->mapWithKeys($mapScope)->toArray();
    }

    public static function getMetricTypeModel(string $scope, string $type): string
    {
        $scopes = self::normalizedScoeps();

        if (!array_key_exists($scope, $scopes)) {
            throw new \Exception("Invalid scope: $scope");
        }
        if (!in_array(
            \App\Models\Metrics\Contracts\HasMetricTypes::class,
            class_implements($scopes[$scope])
        )) {
            throw new \Exception("Scope $scope does not implement HasMetricTypes");
        }

        return app($scopes[$scope])::getMetricTypeModel($type);
    }

    public function getModel(): string|MetricTypeData
    {
        $modelClass = self::getMetricTypeModel($this->scope, $this->type);
        $dto = $modelClass::from($this->config->toArray());
        return $dto;
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(Metric::class);
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('label')
            ->saveSlugsTo('name')
            ->usingSeparator('_')
            ->preventOverwrite()
            ->allowDuplicateSlugs();
    }

    public static function getExtraConfig(?Model $model = null): array
    {
        return [];
    }
}
