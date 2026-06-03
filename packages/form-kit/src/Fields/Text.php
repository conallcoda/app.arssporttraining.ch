<?php

namespace Coda\FormKit\Fields;

use Coda\FormKit\Concerns\HasLiveUpdates;
use Coda\FormKit\Concerns\HasMask;
use Coda\FormKit\Concerns\HasMaxLength;
use Coda\FormKit\Concerns\HasPlaceholder;
use Coda\FormKit\Concerns\HasSuffix;
use Coda\FormKit\Concerns\HasUppercase;
use Coda\FormKit\Field;

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

    public function inputType(string $inputType): static
    {
        $this->inputType = $inputType;

        return $this;
    }

    public function password(): static
    {
        $this->inputType = 'password';

        return $this;
    }
}
