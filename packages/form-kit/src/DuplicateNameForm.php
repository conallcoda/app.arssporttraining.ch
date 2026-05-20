<?php

namespace Coda\FormKit;

use Coda\FormKit\Fields\Text;

class DuplicateNameForm
{
    /** @return array<int, Field> */
    public static function getFields(): array
    {
        return [
            Text::make('name')->label('Name')->rules('required|string|max:255'),
        ];
    }
}
