<?php

namespace Coda\SchemaKit;

use Closure;

/**
 * @method static static make(Closure|string|null $field = null)
 * @method static field(Closure|string|null $field)
 * @method static mediaUuid(Closure|string|null $mediaUuid)
 * @method static mediaVersion(Closure|string|null $mediaVersion)
 * @method static focusPoint(Closure|string|null $focusPoint)
 * @method static preset(?string $preset)
 * @method static widths(array $widths)
 * @method static sizes(?string $sizes)
 * @method static square(bool $square = true)
 * @method static initialsFallback(bool $initialsFallback = true)
 */
class IdentityImage extends IdentityImageDefinition {}
