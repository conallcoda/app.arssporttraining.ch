<?php

namespace App\Livewire;

use Livewire\Component;

class Documentation extends Component
{
    public string $filePath;

    public function mount(): void
    {
        $this->filePath = base_path('app/Models/Training/progression.md');
    }

    public function render()
    {
        return view('livewire.documentation');
    }
}
