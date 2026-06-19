<?php

namespace Coda\SchemaKit;

use Closure;

/**
 * @method static static make()
 * @method static title(Closure|string|null $title)
 * @method static subtitle(Closure|string|null $subtitle)
 * @method static color(Closure|string|null $color)
 * @method static icon(Closure|string|null $icon)
 * @method static image(Closure|string|IdentityImageDefinition|null $image)
 * @method static href(Closure|string|null $href)
 * @method static setMeta(string $key, mixed $value)
 */
class Identity extends IdentityDefinition {}
