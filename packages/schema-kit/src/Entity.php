<?php

namespace Coda\SchemaKit;

/**
 * @method static static make(string $name)
 * @method static model(string $modelClass)
 * @method static label(string $label)
 * @method static pluralLabel(string $pluralLabel)
 * @method static identity(IdentityDefinition|callable|null $configure = null)
 * @method static with(array $relations)
 * @method static mutateWritePayloadUsing(\Closure $closure)
 * @method static toPayloadUsing(\Closure $closure)
 * @method static resolveModelUsing(\Closure $closure)
 * @method static persistUsing(\Closure $closure)
 * @method static facet(FacetDefinition|string $name, ?callable $configure = null)
 * @method static view(ViewDefinition|string $name, ?callable $configure = null)
 * @method static scope(ScopeDefinition|string $scope, ?callable $configure = null)
 * @method static taxonomy(TaxonomyDefinition|string $taxonomy, ?callable $configure = null)
 * @method static setMeta(string $key, mixed $value)
 */
class Entity extends EntityDefinition {}
