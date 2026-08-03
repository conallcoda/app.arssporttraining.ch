<?php

namespace Coda\Cms\Livewire;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

class LoginUserSwitcher extends Component
{
    public string $selectedType = '';

    public string $selectedUserId = '';

    public function mount(): void
    {
        abort_unless(config('athlete.user_switching', false), 403);

        $preferredType = $this->groupedUsers->has('athlete')
            ? 'athlete'
            : ($this->groupedUsers->keys()->first() ?? '');

        $this->selectedType = $preferredType;
        $this->selectedUserId = (string) ($this->availableUsers->first()?->id ?? '');
    }

    public function updatedSelectedType(): void
    {
        $this->selectedUserId = (string) ($this->availableUsers->first()?->id ?? '');
    }

    public function loginAsSelectedUser(): void
    {
        abort_unless(config('athlete.user_switching', false), 403);

        $userId = (int) $this->selectedUserId;
        abort_if($userId <= 0, 422);

        $userModel = config('cms.models.user');
        $user = $userModel::findOrFail($userId);

        Auth::login($user);
        session()->regenerate();

        $redirect = $this->sessionRedirectTarget();

        if ($redirect !== null) {
            $this->redirect($redirect, navigate: true);

            return;
        }

        $homeByType = config('cms.home_by_type');
        $newType = $user->type->value;
        $home = $homeByType[$newType] ?? $homeByType['*'] ?? config('cms.home', '/admin/dashboard');

        $this->redirect($home, navigate: true);
    }

    /** @return Collection<string, string> */
    #[Computed]
    public function userTypeOptions(): Collection
    {
        return $this->groupedUsers
            ->keys()
            ->mapWithKeys(fn (string $type) => [$type => Str::headline($type)]);
    }

    /** @return Collection<int, mixed> */
    #[Computed]
    public function availableUsers(): Collection
    {
        return $this->groupedUsers->get($this->selectedType, collect());
    }

    /** @return Collection<string, Collection<int, mixed>> */
    #[Computed]
    public function groupedUsers(): Collection
    {
        $userModel = config('cms.models.user');

        return $userModel::query()
            ->orderBy('forename')
            ->orderBy('surname')
            ->get()
            ->groupBy(fn ($user): string => $user->type->value);
    }

    public function render()
    {
        return view('cms::livewire.login-user-switcher');
    }

    private function sessionRedirectTarget(): ?string
    {
        $intended = session()->pull('url.intended');

        return is_string($intended) && $intended !== ''
            ? $intended
            : null;
    }
}
