<?php

namespace App\Livewire\Training;

use App\Data\Training\Calendar\CalendarSettingsData;
use Carbon\Carbon;
use Coda\Cms\Form\Form;
use Coda\Cms\Livewire\FormModal;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Computed;

class CalendarRangeForm extends FormModal
{
    public int $weekStartsOn;

    public int $weekEndsOn;

    public function mount(
        string $name = 'calendar-range',
        string $title = 'Calendar Settings',
        ?string $formDataClass = null,
        string $submitLabel = 'Apply',
        string $cancelLabel = 'Cancel',
        bool $flyout = true,
        string $maxWidth = 'max-w-md',
        bool $showDelete = false,
        array $excludeFields = [],
    ): void {
        $this->weekStartsOn = (int) config('training.week_starts_on', Carbon::MONDAY);
        $this->weekEndsOn = ($this->weekStartsOn + 6) % 7;

        parent::mount(
            name: $name,
            title: $title,
            formDataClass: $formDataClass,
            submitLabel: $submitLabel,
            cancelLabel: $cancelLabel,
            flyout: $flyout,
            maxWidth: $maxWidth,
            showDelete: $showDelete,
            excludeFields: $excludeFields,
        );
    }

    public function open(array $data = [], ?string $title = null, ?string $focusField = null, ?int $focusIndex = null): void
    {
        $this->activeTitle = $title;

        unset($this->formConfig, $this->fieldsets);
        $this->openCount++;

        $this->data = [
            'period' => $data['period'] ?? 'month',
            'date' => $data['date'] ?? Carbon::now()->format('Y-m-d'),
            'start' => $data['start'] ?? '',
            'end' => $data['end'] ?? '',
        ];

        Flux::modal($this->name)->show();
    }

    public function updated(string $property, mixed $value): void
    {
        if ($property === 'data.period') {
            $this->snapDateToMode();
            unset($this->fieldsets);
        }

        if ($property === 'data.date' && $value) {
            try {
                $date = Carbon::parse($value);
                $this->data['date'] = match ($this->data['period'] ?? 'month') {
                    'month' => $date->startOfMonth()->format('Y-m-d'),
                    'week' => $date->startOfWeek($this->weekStartsOn)->format('Y-m-d'),
                    default => $date->format('Y-m-d'),
                };
            } catch (\Exception) {
            }
        }

        if ($property === 'data.start' && $value) {
            try {
                $this->data['start'] = Carbon::parse($value)->startOfWeek($this->weekStartsOn)->format('Y-m-d');
            } catch (\Exception) {
            }
        }

        if ($property === 'data.end' && $value) {
            try {
                $this->data['end'] = Carbon::parse($value)->endOfWeek($this->weekEndsOn)->format('Y-m-d');
            } catch (\Exception) {
            }
        }

        parent::updated($property, $value);
    }

    protected function snapDateToMode(): void
    {
        try {
            $date = Carbon::parse($this->data['date']);

            if ($this->data['period'] === 'range') {
                $this->snapToRangePreset($date);

                return;
            }

            $this->data['date'] = match ($this->data['period']) {
                'month' => $date->startOfMonth()->format('Y-m-d'),
                'week' => $date->startOfWeek($this->weekStartsOn)->format('Y-m-d'),
                'day' => Carbon::today()->format('Y-m-d'),
                default => $date->format('Y-m-d'),
            };
        } catch (\Exception) {
        }
    }

    protected function snapToRangePreset(Carbon $date): void
    {
        $now = Carbon::now();

        for ($i = -3; $i <= 3; $i++) {
            $originalStart = $now->copy()->addMonths($i)->startOfMonth();
            $originalEnd = $now->copy()->addMonths($i + 1)->endOfMonth();

            if ($date->between($originalStart, $originalEnd)) {
                $this->data['start'] = $originalStart->copy()->startOfWeek($this->weekStartsOn)->format('Y-m-d');
                $this->data['end'] = $originalEnd->copy()->endOfWeek($this->weekEndsOn)->format('Y-m-d');

                return;
            }
        }

        $originalStart = $date->copy()->startOfMonth();
        $originalEnd = $date->copy()->addMonth()->endOfMonth();
        $this->data['start'] = $originalStart->startOfWeek($this->weekStartsOn)->format('Y-m-d');
        $this->data['end'] = $originalEnd->endOfWeek($this->weekEndsOn)->format('Y-m-d');
    }

    public function submit(): void
    {
        Flux::modal($this->name)->close();

        $this->dispatch("{$this->name}.submitted", data: $this->data);
    }

    #[Computed]
    public function formConfig(): Form
    {
        return CalendarSettingsData::getForm();
    }

    public function render(): View
    {
        return view('livewire.training.calendar-range-form');
    }
}
