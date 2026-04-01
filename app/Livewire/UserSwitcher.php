<?php

namespace App\Livewire;

use App\Models\Users\User;
use App\Models\Users\UserTypeEnum;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class UserSwitcher extends Component
{
    public function switchUser(int $userId): void
    {
        if (! config('app.user_switching')) {
            abort(403);
        }

        $user = User::findOrFail($userId);

        Auth::login($user);
        session()->regenerate();

        $this->redirect(request()->header('Referer', '/'), navigate: true);
    }

    /** @return Collection<string, Collection<int, User>> */
    #[Computed]
    public function groupedUsers(): Collection
    {
        return User::query()
            ->whereIn('type', [UserTypeEnum::Admin, UserTypeEnum::Coach, UserTypeEnum::Athlete])
            ->orderBy('forename')
            ->orderBy('surname')
            ->get()
            ->groupBy(fn (User $user): string => $user->type->value);
    }

    public function render()
    {
        return view('livewire.user-switcher');
    }
}
