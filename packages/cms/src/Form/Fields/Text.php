<?php

namespace Coda\Cms\Form\Fields;

use Coda\Cms\Form\Concerns\HasLiveUpdates;
use Coda\Cms\Form\Concerns\HasMask;
use Coda\Cms\Form\Concerns\HasMaxLength;
use Coda\Cms\Form\Concerns\HasPlaceholder;
use Coda\Cms\Form\Concerns\HasSuffix;
use Coda\Cms\Form\Concerns\HasUppercase;
use Coda\Cms\Form\Field;

class Text extends Field
{
    use HasLiveUpdates;
    use HasMask;
    use HasMaxLength;
    use HasPlaceholder;
    use HasSuffix;
    use HasUppercase;

    public string $type = 'text';

    public string $inputType = 'text';

    public function password(): static
    {
        $this->inputType = 'password';

        return $this;
    }
}
