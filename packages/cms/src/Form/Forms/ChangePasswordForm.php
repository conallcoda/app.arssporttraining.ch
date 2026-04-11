<?php

namespace Coda\Cms\Form\Forms;

use Coda\FormKit\Field;
use Coda\FormKit\Fields\Text;

class ChangePasswordForm
{
    /** @return array<int, Field> */
    public static function getFields(): array
    {
        return [
            Text::make('password')->label('New Password')->password()->rules('required|string|min:8'),
            Text::make('password_confirmation')->label('Confirm Password')->password()->rules('required|string|same:password'),
        ];
    }
}
