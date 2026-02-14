<?php

namespace Coda\Cms\Form\Fields;

use Coda\Cms\Form\Concerns\HasLiveUpdates;
use Coda\Cms\Form\Concerns\HasOptions;
use Coda\Cms\Form\Concerns\HasPlaceholder;
use Coda\Cms\Form\Concerns\HasSelectVariants;
use Coda\Cms\Form\Concerns\HasUnique;
use Coda\Cms\Form\Field;

class Select extends Field
{
    use HasLiveUpdates;
    use HasOptions;
    use HasPlaceholder;
    use HasSelectVariants;
    use HasUnique;

    public string $type = 'select';
}
