<?php

namespace Coda\Cms\Livewire\Auth;

use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Laravel\Passkeys\Events\PasskeyDeleted;
use Laravel\Passkeys\Passkeys;
use Livewire\Attributes\On;
use Livewire\Component;

class ManagePasskeys extends Component
{
    public function delete(int $passkeyId): void
    {
        $passkey = Passkeys::passkeyModel()::query()
            ->where('user_id', Auth::id())
            ->findOrFail($passkeyId);

        $passkey->delete();

        PasskeyDeleted::dispatch($passkey);

        Flux::toast(text: __('Passkey removed'), variant: 'success');
    }

    #[On('passkey-registered')]
    public function refresh(): void
    {
        Flux::toast(text: __('Passkey added'), variant: 'success');
    }

    public function render(): View
    {
        $passkeys = Auth::user()
            ->passkeys()
            ->latest()
            ->get();

        return view('cms::livewire.auth.manage-passkeys', [
            'passkeys' => $passkeys,
        ]);
    }
}
