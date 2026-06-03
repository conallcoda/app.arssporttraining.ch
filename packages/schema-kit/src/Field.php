<?php

namespace Coda\SchemaKit;

use Closure;

/**
 * @method static static make(string $name)
 * @method static attribute(?string $attribute)
 * @method static label(string $label)
 * @method static help(string $help)
 * @method static formType(string $formType)
 * @method static listType(string $listType)
 * @method static placeholder(?string $placeholder)
 * @method static required(bool $required = true)
 * @method static rules(string|array|Closure|null $rules)
 * @method static readable(bool $readable = true)
 * @method static writable(bool $writable = true)
 * @method static formVisible(bool $formVisible = true)
 * @method static readUsing(Closure|string|null $readUsing)
 * @method static writeUsing(?Closure $writeUsing)
 * @method static input(?InputDefinition $input)
 * @method static sortAs(?string $sortAs)
 * @method static suffix(?string $suffix)
 * @method static title(bool $title = true)
 * @method static modal(bool $modal = true)
 * @method static setMeta(string $key, mixed $value)
 */
class Field extends FieldDefinition {}
