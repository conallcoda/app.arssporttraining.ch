<?php

namespace App\Data\Exercise\Settings;

use App\Form\Fields\Sets;
use Coda\FormKit\Fields;
use Illuminate\Support\Facades\Auth;

class SetsSetting extends AbstractSetting
{
    public function __construct(
        public string $deload = 'none',
        public ?int $deloadBy = 1,
        public ?string $label = 'Set',
        public int $default = 4,
    ) {}

    public static function fields(array $data = []): array
    {
        $groupingMode = (string) (Auth::user()?->config->get('settings.session_grouping.mode', 'week') ?? 'week');
        $cycleLabel = $groupingMode === 'groups' ? 'Groups' : 'Weeks';

        return [
            Fields\RadioSegmented::make('deload')
                ->label('Deload')
                ->options([
                    'odd' => 'Odd '.$cycleLabel,
                    'even' => 'Even '.$cycleLabel,
                    'none' => 'No Deload',
                ])
                ->default('none')
                ->live(),
            Sets::make('deloadBy')
                ->label('Deload By')
                ->min(1)
                ->default(1)
                ->show('deload != "none"'),
            Fields\Text::make('label')
                ->label('Label')
                ->default('Set')
                ->suffix('set(s)'),
            Sets::make('default')
                ->label('Default'),
        ];
    }
}
