<?php

use App\Data\Exercise\DropSet;
use App\Data\Exercise\Settings\WeightSetting;

it('renders drop-set weight planner inputs as text fields with comma validation', function () {
    $meta = WeightSetting::inputMeta([
        '_sets' => ['type' => DropSet::SET_TYPE_DROP],
    ]);

    $this->blade('<x-training.exercise-grid-input :meta="$meta" value="6,5,4" />', [
        'meta' => $meta,
    ])
        ->assertSee('type="text"', false)
        ->assertSee('maxlength="31"', false)
        ->assertSee('pattern="\d+(?:\.\d+)?(?:,\d+(?:\.\d+)?)+"', false);
});

it('keeps normal weight planner inputs numeric', function () {
    $meta = WeightSetting::inputMeta([
        '_sets' => ['type' => DropSet::SET_TYPE_NORMAL],
    ]);

    $this->blade('<x-training.exercise-grid-input :meta="$meta" value="6" />', [
        'meta' => $meta,
    ])
        ->assertSee('type="number"', false)
        ->assertSee('step="0.5"', false)
        ->assertDontSee('pattern=', false);
});
