<?php

namespace App\Livewire\Athlete;

use Illuminate\View\View;
use Livewire\Component;

class Calendar extends Component
{
    public string $calendarView = 'day';

    public function mount(string $calendarView = 'day'): void
    {
        $this->calendarView = $calendarView;
    }

    public function render(): View
    {
        return view('livewire.athlete.calendar')
            ->layout('components.layouts.athlete', ['title' => 'Calendar']);
    }
}
