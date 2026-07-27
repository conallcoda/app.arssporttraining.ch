<?php

namespace Coda\FormKit\Fields;

use Coda\FormKit\Concerns\HasLiveUpdates;
use Coda\FormKit\Concerns\HasOptions;
use Coda\FormKit\Concerns\HasPlaceholder;
use Coda\FormKit\Concerns\HasSelectVariants;
use Coda\FormKit\Field;

class Pillbox extends Field
{
    use HasLiveUpdates;
    use HasOptions;
    use HasPlaceholder;
    use HasSelectVariants;

    public string $type = 'pillbox';
}
