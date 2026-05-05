<?php

namespace App\Http\Controllers\Auth;

use App\Models\Users\User;
use Coda\Cms\Http\Responses\TypeAwareLoginResponse;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class UserAccountSetupController extends Controller
{
    public function __construct(
        private readonly StatefulGuard $guard,
    ) {}

    public function create(Request $request, string $accountSetupUuid, string $token): View
    {
        return view('auth.user-account-setup', [
            'user' => $this->findUserOrFail($accountSetupUuid),
            'token' => $token,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'account_setup_uuid' => 'required|uuid',
            'token' => 'required',
            'password' => 'required|confirmed',
        ]);

        $user = User::query()
            ->where('account_setup_uuid', $request->string('account_setup_uuid')->toString())
            ->first();

        if ($user === null || ! $user->hasValidAccountSetupToken($request->string('token')->toString())) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['token' => __('This setup link is invalid or has expired.')]);
        }

        $user->completeAccountSetup($request->string('password')->toString());
        $this->guard->login($user);
        $request->session()->regenerate();

        return app(TypeAwareLoginResponse::class)->toResponse($request);
    }

    private function findUserOrFail(string $accountSetupUuid): User
    {
        return User::query()
            ->where('account_setup_uuid', $accountSetupUuid)
            ->firstOrFail();
    }
}
