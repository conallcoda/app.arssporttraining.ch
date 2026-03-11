<?php

namespace App\Form;

use Coda\Cms\Form\Field;
use Coda\Cms\Form\Fields\Text;

class AdminChangePasswordForm
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
