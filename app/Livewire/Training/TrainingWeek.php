<?php

namespace App\Livewire\Training;

use App\Filament\Forms\Components\ColorPicker;
use App\Models\Training\Periods\TrainingWeek as TrainingWeekDTO;
use Livewire\Component;

class TrainingWeek extends Component
{
    public TrainingWeekDTO $week;

    public function render()
    {
        return view('livewire.training.training-week');
    }

    public function getColorValue(?string $colorName): string
    {
        if (!$colorName) {
            return '#000000';
        }
        return ColorPicker::getColorValue($colorName);
    }
}
