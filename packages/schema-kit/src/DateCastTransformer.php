<?php

namespace Coda\SchemaKit;

use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Casts\IterableItemCast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;
use Spatie\LaravelData\Transformers\Transformer;

final class DateCastTransformer implements Cast, IterableItemCast, Transformer
{
    /**
     * @param  array<int, string>|string|null  $inputFormats
     */
    public function __construct(
        private readonly array|string|null $inputFormats = null,
        private readonly ?string $outputFormat = null,
        private readonly ?string $type = null,
        private readonly ?string $setTimeZone = null,
        private readonly ?string $timeZone = null,
    ) {}

    public function cast(DataProperty $property, mixed $value, array $properties, CreationContext $context): mixed
    {
        return $this->castDelegate()->cast($property, $value, $properties, $context);
    }

    public function castIterableItem(DataProperty $property, mixed $value, array $properties, CreationContext $context): mixed
    {
        return $this->castDelegate()->castIterableItem($property, $value, $properties, $context);
    }

    public function transform(DataProperty $property, mixed $value, TransformationContext $context): mixed
    {
        return $this->transformerDelegate()->transform($property, $value, $context);
    }

    private function castDelegate(): DateTimeInterfaceCast
    {
        return new DateTimeInterfaceCast(
            format: $this->inputFormats ?? ['Y-m-d', DATE_ATOM],
            type: $this->type,
            setTimeZone: $this->setTimeZone,
            timeZone: $this->timeZone,
        );
    }

    private function transformerDelegate(): DateTimeInterfaceTransformer
    {
        return new DateTimeInterfaceTransformer(
            format: $this->outputFormat ?? 'Y-m-d',
            setTimeZone: $this->setTimeZone,
        );
    }
}
