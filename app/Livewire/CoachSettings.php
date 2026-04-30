<?php

namespace App\Livewire;

use App\Data\Coach\Settings\AbstractCoachSetting;
use App\Data\Coach\Settings\CalendarSidebarSetting;
use App\Data\Coach\Settings\SessionGroupingSetting;
use Coda\Cms\Livewire\Concerns\InteractsWithFormData;
use Coda\FormKit\Form;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class CoachSettings extends Component
{
    use InteractsWithFormData;

    public array $data = [];

    /** @var list<class-string<AbstractCoachSetting>> */
    protected const SETTINGS = [
        CalendarSidebarSetting::class,
        SessionGroupingSetting::class,
    ];

    public function mount(): void
    {
        $user = Auth::user();

        foreach (static::SETTINGS as $settingClass) {
            $key = $settingClass::fieldsetKey();
            $stored = $user->config->get("settings.{$key}");

            $setting = $stored ? $settingClass::from($stored) : new $settingClass;

            $this->data[$key] = $setting->toArray();
        }
    }

    #[Computed]
    public function formConfig(): Form
    {
        $form = Form::make();

        foreach (static::SETTINGS as $settingClass) {
            $key = $settingClass::fieldsetKey();
            $form->fieldset($settingClass::getName(), $settingClass::fields(), prefix: "data.{$key}");
        }

        return $form;
    }

    #[Computed]
    public function fieldsets(): array
    {
        return $this->formConfig->resolveFieldsets($this->data);
    }

    public function save(): void
    {
        $rules = $this->buildValidationRulesFromFieldsets();

        if (! empty($rules)) {
            $this->validate($rules, [], $this->buildValidationAttributesFromFieldsets());
        }

        $user = Auth::user();

        foreach (static::SETTINGS as $settingClass) {
            $key = $settingClass::fieldsetKey();
            $user->config->set("settings.{$key}", $this->data[$key] ?? []);
        }

        $user->save();

        $this->dispatch('coach-settings-saved');

        Flux::toast(text: __('Settings saved'), variant: 'success');
        Flux::modal('coach-settings')->close();
    }

    public function render(): View
    {
        return view('livewire.coach-settings');
    }
}
