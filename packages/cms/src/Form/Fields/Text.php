<?php

namespace Coda\Cms\Form\Fields;

use Coda\Cms\Form\Concerns\HasLiveUpdates;
use Coda\Cms\Form\Concerns\HasMask;
use Coda\Cms\Form\Concerns\HasPlaceholder;
use Coda\Cms\Form\Concerns\HasSuffix;
use Coda\Cms\Form\Field;

class Text extends Field
{
    use HasLiveUpdates;
    use HasMask;
    use HasPlaceholder;
    use HasSuffix;

    public string $type = 'text';
}
