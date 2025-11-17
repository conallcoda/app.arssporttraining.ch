<?php

namespace App\Models\Metrics;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Filament\Forms;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Spatie\SchemalessAttributes\SchemalessAttributes;
use Spatie\SchemalessAttributes\SchemalessAttributesTrait;


class Metric extends Model
{
    use HasFactory, SoftDeletes, SchemalessAttributesTrait;

    protected $fillable = [
        'metric_type_id',
        'metricable_type',
        'metricable_id',
        'value',
        'extra'
    ];

    public $schemalessAttributes = [
        'value',
        'extra',
    ];

    public function scopeWithExtra(): Builder
    {
        return $this->extra->modelScope();
    }

    public function metricable(): MorphTo
    {
        return $this->morphTo();
    }

    public function metricType(): BelongsTo
    {
        return $this->belongsTo(MetricType::class);
    }

    public static function getExtraConfig(?Model $model = null): array
    {
        return [];
    }

    public static function createForm(Schema $schema, string $scope)
    {


        $typeField = Forms\Components\Select::make('metric_type_id')
            ->label('Metric Type')
            ->options(function () use ($scope) {
                return MetricType::where('scope', $scope)
                    ->pluck('label', 'id');
            })
            ->required()
            ->columnSpanFull()
            ->searchable()
            ->live()
            ->afterStateUpdated(function ($state, $set, $get) {
                if (!$state) {
                    return;
                }

                $metricType = MetricType::find($state);
                if ($metricType) {
                    $dto = $metricType->getModel();
                    $fields = $dto->recordFields($get);

                    foreach ($fields as $field) {
                        $fieldName = $field->getName();
                        $set($fieldName, null);
                    }
                }
            });

        $getSchema = function (Get $get) use ($scope, $typeField) {
            $metricTypeId = $get('metric_type_id');
            $metricType = MetricType::find($metricTypeId);
            $additionalFields = [];
            if ($metricType) {
                $dto = $metricType->getModel();
                $additionalFields = $dto->recordFields($get);
            }

            $defaultFields =  [
                $typeField
            ];


            return array_merge($defaultFields, $additionalFields);
        };
        return $schema
            ->components(
                [
                    Fieldset::make(null)->contained(false)->schema($getSchema)->columnSpanFull(),
                ]
            );
    }
}
