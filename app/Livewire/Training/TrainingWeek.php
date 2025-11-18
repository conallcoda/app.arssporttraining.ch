<?php

namespace App\Livewire\Training;

use App\Filament\Forms\Components\ColorPicker;
use App\Models\Training\TrainingNode;
use Livewire\Component;

class TrainingWeek extends Component
{
    public TrainingNode $week;

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
