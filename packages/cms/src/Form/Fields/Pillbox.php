<?php

namespace Coda\Cms\Form\Fields;

use Coda\Cms\Form\Concerns\HasLiveUpdates;
use Coda\Cms\Form\Concerns\HasOptions;
use Coda\Cms\Form\Concerns\HasPlaceholder;
use Coda\Cms\Form\Concerns\HasSelectVariants;
use Coda\Cms\Form\Field;

class Pillbox extends Field
{
    use HasLiveUpdates;
    use HasOptions;
    use HasPlaceholder;
    use HasSelectVariants;

    public string $type = 'pillbox';
}
