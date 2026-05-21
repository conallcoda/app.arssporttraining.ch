<?php

namespace Coda\Cms\Livewire;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class UserSwitcher extends Component
{
    public function switchUser(int $userId): void
    {
        if (! config('cms.user_switching')) {
            abort(403);
        }

        $previousType = auth()->user()->type->value;

        $userModel = config('cms.models.user');
        $user = $userModel::findOrFail($userId);

        Auth::login($user);
        session()->regenerate();

        $referer = request()->header('Referer', '/');
        $newType = $user->type->value;

        if (str_contains($referer, '/dev/mobile-preview')) {
            $homeByType = config('cms.home_by_type');
            $previewUrl = ($newType !== $previousType && $homeByType)
                ? ($homeByType[$newType] ?? $homeByType['*'] ?? config('cms.home', '/admin/dashboard'))
                : (request()->query('url', '/'));

            $this->redirect(route('cms.mobile-preview', ['url' => $previewUrl]), navigate: true);

            return;
        }

        if ($newType !== $previousType && $homeByType = config('cms.home_by_type')) {
            $home = $homeByType[$newType] ?? $homeByType['*'] ?? config('cms.home', '/admin/dashboard');
            $this->redirect($home, navigate: true);

            return;
        }

        $this->redirect($referer, navigate: true);
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
        return view('cms::livewire.user-switcher');
    }
}
