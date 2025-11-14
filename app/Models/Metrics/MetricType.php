<?php

namespace App\Models\Metrics;

use App\Models\Exercise\Exercise;
use App\Models\Metrics\Contracts\HasMetricTypes;
use App\Models\Users\User;
use App\Rules\ValidModelSubType;
use App\Rules\ValidMetricType;
use Database\Factories\MetricTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Concerns\HasExtraData;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Parental\HasChildren;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MetricType extends Model
{
    use HasFactory, HasExtraData, HasChildren, SoftDeletes;

    protected static function newFactory(): MetricTypeFactory
    {
        return MetricTypeFactory::new();
    }

    protected $fillable = [
        'model_base',
        'model_sub',
        'type',
        'name',
    ];

    protected $childTypes = [
        'boolean' => Types\Boolean::class,
        'duration' => Types\Duration::class,
        'height' => Types\Height::class,
        'number' => Types\Number::class,
        'one_rep_max' => Types\OneRepMax::class,
        'percentage' => Types\Percentage::class,
        'time_under_tension' => Types\TimeUnderTension::class,
        'weight' => Types\Weight::class,
    ];

    public function metrics(): HasMany
    {
        return $this->hasMany(Metric::class);
    }

    public static function getExtraConfig(?Model $model = null): array
    {
        return [];
    }

    protected static function boot()
    {
        parent::boot();

        static::saving(function (MetricType $metricType) {
            $validator = Validator::make([
                'model_base' => $metricType->model_base,
                'model_sub' => $metricType->model_sub,
                'type' => $metricType->type,
            ], [
                'model_base' => ['required', 'string'],
                'model_sub' => [
                    'nullable',
                    'string',
                    new ValidModelSubType($metricType->model_base),
                ],
                'type' => [
                    'required',
                    'string',
                    new ValidMetricType($metricType->model_base, $metricType->model_sub),
                ],
            ]);

            if ($validator->fails()) {
                throw new ValidationException($validator);
            }
        });
    }

    protected static function getModelClassMap(): array
    {
        return [
            'user' => User::class,
            'exercise' => Exercise::class,
        ];
    }

    public static function resolveModelClass(string $modelIdentifier): ?string
    {

        if (class_exists($modelIdentifier)) {
            return $modelIdentifier;
        }

        $map = static::getModelClassMap();
        return $map[$modelIdentifier] ?? null;
    }

    public static function getChildTypesForModel(string $modelClass): array
    {
        $resolvedClass = static::resolveModelClass($modelClass);

        if (!$resolvedClass || !class_exists($resolvedClass)) {
            return [];
        }

        $instance = new $resolvedClass();

        if (method_exists($instance, 'getChildTypes')) {
            return array_keys($instance->getChildTypes());
        }

        return [];
    }

    public function hasValidModelSub(): bool
    {
        if (empty($this->model_sub)) {
            return true;
        }

        $validSubTypes = static::getChildTypesForModel($this->model_base);
        return in_array($this->model_sub, $validSubTypes, true);
    }

    public function getAvailableSubTypes(): array
    {
        return static::getChildTypesForModel($this->model_base);
    }

    public static function getChildTypesWithMetrics(string $modelClass): array
    {
        $resolvedClass = static::resolveModelClass($modelClass);

        if (!$resolvedClass || !class_exists($resolvedClass)) {
            return [];
        }

        $instance = new $resolvedClass();

        if (!method_exists($instance, 'getChildTypes')) {
            return [];
        }

        $childTypes = $instance->getChildTypes();
        $filtered = [];

        foreach ($childTypes as $key => $className) {
            if (in_array(HasMetricTypes::class, class_implements($className) ?: [])) {
                $filtered[$key] = $className;
            }
        }

        return array_keys($filtered);
    }

    public static function getAllowedMetricTypesFor(string $modelBase, ?string $modelSub = null): array
    {
        $resolvedClass = static::resolveModelClass($modelBase);

        if (!$resolvedClass || !class_exists($resolvedClass)) {
            return [];
        }

        if ($modelSub) {
            $instance = new $resolvedClass();
            if (method_exists($instance, 'getChildTypes')) {
                $childTypes = $instance->getChildTypes();
                if (isset($childTypes[$modelSub])) {
                    $resolvedClass = $childTypes[$modelSub];
                }
            }
        }

        if (!in_array(HasMetricTypes::class, class_implements($resolvedClass) ?: [])) {
            return [];
        }

        return $resolvedClass::getAllowedMetricTypes();
    }
}
