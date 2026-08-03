<?php

namespace Coda\SchemaKit;

/**
 * @method static static make(string $name)
 * @method static label(string $label)
 * @method static facets(array $facetNames)
 * @method static appendFacets(array $facetNames)
 * @method static show(array $fieldNames)
 * @method static table(TableViewDefinition|callable|null $configure = null)
 * @method static details(DetailsViewDefinition|callable|null $configure = null)
 * @method static formModalWidth(?string $width)
 * @method static setMeta(string $key, mixed $value)
 */
class View extends ViewDefinition {}
