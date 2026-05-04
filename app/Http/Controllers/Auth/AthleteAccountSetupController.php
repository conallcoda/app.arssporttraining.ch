<?php

namespace App\Http\Controllers\Auth;

use App\Models\Users\User;
use App\Models\Users\UserTypeEnum;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class AthleteAccountSetupController extends Controller
{
    public function __construct(
        private readonly StatefulGuard $guard,
    ) {}

    public function create(Request $request, string $accountSetupUuid, string $token): View
    {
        return view('auth.account-setup', [
            'athlete' => $this->findAthleteOrFail($accountSetupUuid),
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

        $athlete = User::query()
            ->where('account_setup_uuid', $request->string('account_setup_uuid')->toString())
            ->where('type', UserTypeEnum::Athlete)
            ->first();

        if ($athlete === null || ! $athlete->hasValidAccountSetupToken($request->string('token')->toString())) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['token' => __('This setup link is invalid or has expired.')]);
        }

        $athlete->completeAccountSetup($request->string('password')->toString());
        $this->guard->login($athlete);
        $request->session()->regenerate();

        return redirect()->route('athlete.dashboard');
    }

    private function findAthleteOrFail(string $accountSetupUuid): User
    {
        return User::query()
            ->where('account_setup_uuid', $accountSetupUuid)
            ->where('type', UserTypeEnum::Athlete)
            ->firstOrFail();
    }
}
