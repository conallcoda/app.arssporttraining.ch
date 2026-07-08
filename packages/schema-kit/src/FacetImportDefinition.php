<?php

namespace Coda\SchemaKit;

use Closure;

final class FacetImportDefinition
{
    public function __construct(
        private readonly string $sourceEntity,
        private readonly string $sourceFacet,
        private readonly string $localName,
        private readonly string $localDataPath,
        private readonly ?Closure $configure = null,
    ) {}

    public function sourceEntity(): string
    {
        return $this->sourceEntity;
    }

    public function sourceFacet(): string
    {
        return $this->sourceFacet;
    }

    public function localName(): string
    {
        return $this->localName;
    }

    public function localDataPath(): string
    {
        return $this->localDataPath;
    }

    public function configure(): ?Closure
    {
        return $this->configure;
    }
}
