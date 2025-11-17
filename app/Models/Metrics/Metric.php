<?php

namespace App\Models\Metrics;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\SchemalessAttributes\SchemalessAttributes;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Flex;

class Metric extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'metric_type_id',
        'metricable_type',
        'metricable_id',
        'value',
    ];

    public function initializeHasExtraData()
    {
        $this->casts['value'] = SchemalessAttributes::class;
    }

    public function scopeWithExtraAttributes(): Builder
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
            ->live();

        $getSchema = function (Get $get) use ($scope, $typeField) {
            $metricTypeId = $get('metric_type_id');
            $metricType = MetricType::find($metricTypeId);
            $additionalFields = [];
            if ($metricType) {
                $dto = $metricType->getModel();
                $additionalFields = $dto->recordFields();
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
