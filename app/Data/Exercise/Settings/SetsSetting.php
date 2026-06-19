<?php

namespace App\Data\Exercise\Settings;

use App\Data\Exercise\DropSet;
use App\Form\Fields\Sets;
use App\Data\Exercise\Preview\SessionGroupingMode;
use Coda\FormKit\Fields;
use Illuminate\Support\Facades\Auth;

class SetsSetting extends AbstractSetting
{
    public function __construct(
        public string $type = DropSet::SET_TYPE_NORMAL,
        public string $deload = 'none',
        public ?int $deloadBy = 1,
        public ?string $label = 'Set',
        public int $default = 4,
    ) {}

    public static function fields(array $data = []): array
    {
        $groupingMode = SessionGroupingMode::normalizeMode(
            (string) (Auth::user()?->config->get('settings.session_grouping.mode', SessionGroupingMode::defaultMode()) ?? SessionGroupingMode::defaultMode())
        );
        $cycleLabel = SessionGroupingMode::cycleLabel($groupingMode);

        return [
            Fields\RadioSegmented::make('type')
                ->label('Set Type')
                ->options([
                    DropSet::SET_TYPE_NORMAL => 'Normal Set',
                    DropSet::SET_TYPE_DROP => 'Drop Set',
                ])
                ->default(DropSet::SET_TYPE_NORMAL)
                ->live(),
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
                ->rules('nullable|integer|min:1')
                ->show('deload != "none"'),
            Fields\Text::make('label')
                ->label('Label')
                ->default('Set')
                ->suffix('set(s)'),
            Sets::make('default')
                ->label('Default')
                ->rules('required|integer|min:1'),
        ];
    }
}
