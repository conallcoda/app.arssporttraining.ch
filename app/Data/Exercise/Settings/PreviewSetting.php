<?php

namespace App\Data\Exercise\Settings;

use App\Data\Exercise\Preview\SessionGroupingMode;
use Coda\FormKit\Fields;
use Illuminate\Support\Facades\Auth;

class PreviewSetting extends AbstractSetting
{
    public function __construct(
        public int $weeks = 1,
        public int $sessionsPerWeek = 1,
        public ?int $measuredReps = null,
        public ?float $measuredWeight = null,
        public ?int $targetGoal = null,
    ) {
        $this->measuredReps ??= 1;
        $this->measuredWeight ??= 50;
        $this->targetGoal ??= 10;
    }

    public static function fields(array $data = []): array
    {
        $groupingMode = SessionGroupingMode::normalizeMode(
            (string) (Auth::user()?->config->get('settings.session_grouping.mode', SessionGroupingMode::defaultMode()) ?? SessionGroupingMode::defaultMode())
        );
        $usesWeekBuckets = $groupingMode === SessionGroupingMode::Week->value;

        $fields = [
            Fields\Number::make('weeks')
                ->label('Planned Groups')
                ->min(1)
                ->max(12)
                ->step(1)
                ->default(1)
                ->suffix(SessionGroupingMode::plannedGroupsSuffix($groupingMode)),
        ];

        if ($usesWeekBuckets) {
            $fields[] = Fields\Number::make('sessionsPerWeek')
                ->label('Sessions Per Week')
                ->min(1)
                ->max(7)
                ->step(1)
                ->default(1)
                ->suffix('session(s)');
        }

        $hasAutomaticWeight = in_array('weight', $data['config']['settings'] ?? [])
            && ($data['config']['weight']['mode'] ?? 'manual') === 'automatic';

        if ($hasAutomaticWeight) {
            $fields = array_merge($fields, WeightProgressionSetting::fields());
        }

        return $fields;
    }
}
