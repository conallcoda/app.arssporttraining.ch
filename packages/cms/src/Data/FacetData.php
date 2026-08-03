<?php

namespace Coda\Cms\Data;

use Coda\SchemaKit\Facet;
use Coda\SchemaKit\FacetDefinition;
use Illuminate\Support\Str;

abstract class FacetData extends AbstractData
{
    public static function make(?string $name = null, ?string $dataPath = null): Facet
    {
        $facet = Facet::make($name ?? static::facetName())
            ->data(static::class);

        static::configureFacet($facet);

        if (($defaultDataPath = static::facetDataPath()) !== null) {
            $facet->dataPath($defaultDataPath);
        }

        if ($dataPath !== null) {
            $facet->dataPath($dataPath);
        }

        return $facet;
    }

    public static function facet(?string $name = null, ?string $dataPath = null): Facet
    {
        return static::make($name, $dataPath);
    }

    public static function facetName(): string
    {
        $baseName = preg_replace('/(Data|Facet)$/', '', class_basename(static::class)) ?: class_basename(static::class);

        return Str::snake($baseName);
    }

    public static function facetLabel(): ?string
    {
        $baseName = preg_replace('/(Data|Facet)$/', '', class_basename(static::class)) ?: class_basename(static::class);

        return Str::headline($baseName);
    }

    public static function facetDataPath(): ?string
    {
        return 'data.'.Str::camel(static::facetName());
    }

    protected static function configureFacet(FacetDefinition $facet): void
    {
        $label = static::facetLabel();

        if ($label !== null && $facet->getLabel() === null) {
            $facet->label($label);
        }
    }
}
