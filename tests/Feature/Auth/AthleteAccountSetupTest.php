<?php

use App\Livewire\Database\AthleteList;
use App\Models\Users\AccountSetupStatus;
use App\Models\Users\User;
use App\Notifications\AthleteAccountSetupNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

it('sends an athlete account setup email from the athlete list', function () {
    Notification::fake();

    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create([
        'email' => 'athlete@example.com',
        'password' => null,
        'owner_id' => $coach->id,
    ]);

    $this->actingAs($coach);

    Livewire::test(AthleteList::class)
        ->call('sendSetupAccountEmail', $athlete->id);

    Notification::assertSentTo(
        $athlete,
        AthleteAccountSetupNotification::class,
        fn (AthleteAccountSetupNotification $notification): bool => str_contains($notification->setupUrl($athlete), $athlete->account_setup_uuid)
    );

    $athlete->refresh();

    expect($athlete->account_setup_token_hash)->not->toBeNull()
        ->and($athlete->account_setup_sent_at)->not->toBeNull()
        ->and($athlete->account_setup_expires_at?->isSameDay(now()->addDays((int) config('athlete.account_setup_expiry_days'))))->toBeTrue()
        ->and($athlete->accountSetupStatus())->toBe(AccountSetupStatus::SetupEmailSent);
});

it('lets an athlete set a password from the setup link and logs them into the dashboard', function () {
    $athlete = User::factory()->athlete()->create([
        'email' => 'athlete@example.com',
        'password' => null,
    ]);

    $token = $athlete->issueAccountSetupToken();

    $response = $this->post(route('athlete.account-setup.store'), [
        'account_setup_uuid' => $athlete->account_setup_uuid,
        'token' => $token,
        'password' => 'NewSecurePass123!',
        'password_confirmation' => 'NewSecurePass123!',
    ]);

    $response->assertRedirect(route('athlete.dashboard', absolute: false));

    $this->assertAuthenticatedAs($athlete);
    expect(Hash::check('NewSecurePass123!', $athlete->fresh()->password))->toBeTrue()
        ->and($athlete->fresh()->account_setup_completed_at)->not->toBeNull()
        ->and($athlete->fresh()->account_setup_token_hash)->toBeNull()
        ->and($athlete->fresh()->accountSetupStatus())->toBe(AccountSetupStatus::Active);
});

it('rejects expired setup links', function () {
    Carbon::setTestNow('2026-05-04 10:00:00');

    $athlete = User::factory()->athlete()->create([
        'email' => 'athlete@example.com',
        'password' => null,
    ]);

    $token = $athlete->issueAccountSetupToken();

    Carbon::setTestNow(now()->addDays((int) config('athlete.account_setup_expiry_days', 30) + 1));

    $response = $this->from(route('athlete.account-setup', [
        'accountSetupUuid' => $athlete->account_setup_uuid,
        'token' => $token,
    ]))->post(route('athlete.account-setup.store'), [
        'account_setup_uuid' => $athlete->account_setup_uuid,
        'token' => $token,
        'password' => 'ExpiredPass123!',
        'password_confirmation' => 'ExpiredPass123!',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('token');
    $this->assertGuest();

    Carbon::setTestNow();
});

it('shows and filters setup status values in the athlete list', function () {
    $coach = User::factory()->coach()->create();

    User::factory()->athlete()->create([
        'forename' => 'Email',
        'surname' => 'Missing',
        'email' => null,
        'owner_id' => $coach->id,
    ]);

    User::factory()->athlete()->create([
        'forename' => 'Setup',
        'surname' => 'NotSent',
        'email' => 'not-sent@example.com',
        'owner_id' => $coach->id,
    ]);

    $sent = User::factory()->athlete()->create([
        'forename' => 'Setup',
        'surname' => 'Sent',
        'email' => 'sent@example.com',
        'owner_id' => $coach->id,
    ]);
    $sent->issueAccountSetupToken();

    $expired = User::factory()->athlete()->create([
        'forename' => 'Setup',
        'surname' => 'Expired',
        'email' => 'expired@example.com',
        'owner_id' => $coach->id,
    ]);
    $expired->issueAccountSetupToken();
    $expired->forceFill([
        'account_setup_expires_at' => now()->subDay(),
    ])->save();

    $active = User::factory()->athlete()->create([
        'forename' => 'Active',
        'surname' => 'Athlete',
        'email' => 'active@example.com',
        'owner_id' => $coach->id,
    ]);
    $active->forceFill([
        'account_setup_completed_at' => now()->subDay(),
    ])->save();

    $this->actingAs($coach);

    Livewire::test(AthleteList::class)
        ->assertSee('Email Missing')
        ->assertSee('Setup Email Not Sent')
        ->assertSee('Setup Email Sent')
        ->assertSee('Setup Email Expired')
        ->assertSee('Active')
        ->set('filters.setup_status', AccountSetupStatus::SetupEmailExpired->value)
        ->assertSee('Setup Email Expired')
        ->assertSee('Expired, Setup')
        ->assertDontSee('Sent, Setup')
        ->assertDontSee('NotSent, Setup')
        ->assertDontSee('Athlete, Active')
        ->assertDontSee('Missing, Email');
});
