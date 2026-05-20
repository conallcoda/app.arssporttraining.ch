<?php

namespace Coda\Cms\Livewire\Auth;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class ChangePassword extends Component
{
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function save(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
        ]);

        Auth::user()->update([
            'password' => Hash::make($this->password),
        ]);

        $this->reset();

        Flux::toast(text: __('Password changed successfully'), variant: 'success');

        Flux::modal('change-password')->close();
    }

    public function render(): View
    {
        return view('cms::livewire.auth.change-password');
    }
}
