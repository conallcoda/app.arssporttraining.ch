<?php

use App\Livewire\Database\AthleteList;
use App\Livewire\Database\CoachList;
use App\Models\Users\AccountSetupStatus;
use App\Models\Users\User;
use App\Notifications\AccountSetupNotification;
use Carbon\Carbon;
use Coda\Cms\Form\Forms\ChangePasswordForm;
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
        AccountSetupNotification::class,
        fn (AccountSetupNotification $notification): bool => str_contains($notification->setupUrl($athlete), $athlete->account_setup_uuid)
    );

    $athlete->refresh();

    expect($athlete->account_setup_token_hash)->not->toBeNull()
        ->and($athlete->account_setup_sent_at)->not->toBeNull()
        ->and($athlete->account_setup_expires_at?->isSameDay(now()->addDays((int) config('user.account_setup_expiry_days'))))->toBeTrue()
        ->and($athlete->accountSetupStatus())->toBe(AccountSetupStatus::SetupEmailSent);
});

it('resends an athlete setup email and resets active setup state', function () {
    Notification::fake();

    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create([
        'email' => 'athlete@example.com',
        'owner_id' => $coach->id,
        'account_setup_token_hash' => hash('sha256', 'old-token'),
        'account_setup_sent_at' => now()->subMonths(2),
        'account_setup_expires_at' => null,
        'account_setup_completed_at' => now()->subMonth(),
    ]);

    $this->actingAs($coach);

    Livewire::test(AthleteList::class)
        ->call('sendSetupAccountEmail', $athlete->id);

    Notification::assertSentTo($athlete, AccountSetupNotification::class);

    $athlete->refresh();

    expect($athlete->account_setup_token_hash)->not->toBeNull()
        ->and($athlete->account_setup_token_hash)->not->toBe(hash('sha256', 'old-token'))
        ->and($athlete->account_setup_sent_at)->not->toBeNull()
        ->and($athlete->account_setup_expires_at?->isSameDay(now()->addDays((int) config('user.account_setup_expiry_days'))))->toBeTrue()
        ->and($athlete->account_setup_completed_at)->toBeNull()
        ->and($athlete->accountSetupStatus())->toBe(AccountSetupStatus::SetupEmailSent);
});

it('renders athlete row menu setup email as a direct action and uses the shared change password form', function () {
    $coach = User::factory()->coach()->create();
    $athlete = User::factory()->athlete()->create([
        'email' => 'athlete@example.com',
        'password' => null,
        'owner_id' => $coach->id,
    ]);

    $this->actingAs($coach);

    $component = Livewire::test(AthleteList::class);

    expect(collect($component->instance()->formModals)->pluck('formDataClass')->all())
        ->toContain(ChangePasswordForm::class);

    $component->assertSeeHtml('wire:click="sendSetupAccountEmail('.$athlete->id.')');
});

it('lets an athlete set a password from the setup link and logs them into the dashboard', function () {
    $athlete = User::factory()->athlete()->create([
        'email' => 'athlete@example.com',
        'password' => null,
    ]);

    $token = $athlete->issueAccountSetupToken();

    $response = $this->post(route('user.account-setup.store'), [
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

it('sends a coach account setup email from the coach list', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $coach = User::factory()->coach()->create([
        'email' => 'coach@example.com',
        'password' => null,
    ]);

    $this->actingAs($admin);

    Livewire::test(CoachList::class)
        ->call('sendSetupAccountEmail', $coach->id);

    Notification::assertSentTo(
        $coach,
        AccountSetupNotification::class,
        fn (AccountSetupNotification $notification): bool => str_contains($notification->setupUrl($coach), $coach->account_setup_uuid)
    );

    $coach->refresh();

    expect($coach->account_setup_token_hash)->not->toBeNull()
        ->and($coach->account_setup_sent_at)->not->toBeNull()
        ->and($coach->account_setup_expires_at?->isSameDay(now()->addDays((int) config('user.account_setup_expiry_days'))))->toBeTrue()
        ->and($coach->accountSetupStatus())->toBe(AccountSetupStatus::SetupEmailSent);
});

it('resends a coach setup email and resets active setup state', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $coach = User::factory()->coach()->create([
        'email' => 'coach@example.com',
        'account_setup_token_hash' => hash('sha256', 'old-token'),
        'account_setup_sent_at' => now()->subMonths(2),
        'account_setup_expires_at' => null,
        'account_setup_completed_at' => now()->subMonth(),
    ]);

    $this->actingAs($admin);

    Livewire::test(CoachList::class)
        ->call('sendSetupAccountEmail', $coach->id);

    Notification::assertSentTo($coach, AccountSetupNotification::class);

    $coach->refresh();

    expect($coach->account_setup_token_hash)->not->toBeNull()
        ->and($coach->account_setup_token_hash)->not->toBe(hash('sha256', 'old-token'))
        ->and($coach->account_setup_sent_at)->not->toBeNull()
        ->and($coach->account_setup_expires_at?->isSameDay(now()->addDays((int) config('user.account_setup_expiry_days'))))->toBeTrue()
        ->and($coach->account_setup_completed_at)->toBeNull()
        ->and($coach->accountSetupStatus())->toBe(AccountSetupStatus::SetupEmailSent);
});

it('renders coach row menu setup email as a direct action and uses the shared change password form', function () {
    $admin = User::factory()->admin()->create();
    $coach = User::factory()->coach()->create([
        'email' => 'coach@example.com',
        'password' => null,
    ]);

    $this->actingAs($admin);

    $component = Livewire::test(CoachList::class);

    expect(collect($component->instance()->formModals)->pluck('formDataClass')->all())
        ->toContain(ChangePasswordForm::class);

    $component->assertSeeHtml('wire:click="sendSetupAccountEmail('.$coach->id.')');
});

it('lets a coach set a password from the setup link and logs them into admin', function () {
    $coach = User::factory()->coach()->create([
        'email' => 'coach@example.com',
        'password' => null,
    ]);

    $token = $coach->issueAccountSetupToken();

    $response = $this->post(route('user.account-setup.store'), [
        'account_setup_uuid' => $coach->account_setup_uuid,
        'token' => $token,
        'password' => 'NewSecurePass123!',
        'password_confirmation' => 'NewSecurePass123!',
    ]);

    $response->assertRedirect('/admin/programs');

    $this->assertAuthenticatedAs($coach);
    expect(Hash::check('NewSecurePass123!', $coach->fresh()->password))->toBeTrue()
        ->and($coach->fresh()->account_setup_completed_at)->not->toBeNull()
        ->and($coach->fresh()->account_setup_token_hash)->toBeNull()
        ->and($coach->fresh()->accountSetupStatus())->toBe(AccountSetupStatus::Active);
});

it('rejects expired setup links', function () {
    Carbon::setTestNow('2026-05-04 10:00:00');

    $athlete = User::factory()->athlete()->create([
        'email' => 'athlete@example.com',
        'password' => null,
    ]);

    $token = $athlete->issueAccountSetupToken();

    Carbon::setTestNow(now()->addDays((int) config('user.account_setup_expiry_days', 30) + 1));

    $response = $this->from(route('user.account-setup', [
        'accountSetupUuid' => $athlete->account_setup_uuid,
        'token' => $token,
    ]))->post(route('user.account-setup.store'), [
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

it('shows and filters setup status values in the coach list', function () {
    $admin = User::factory()->admin()->create();

    User::factory()->coach()->create([
        'forename' => 'Email',
        'surname' => 'Missing',
        'email' => null,
    ]);

    User::factory()->coach()->create([
        'forename' => 'Setup',
        'surname' => 'NotSent',
        'email' => 'not-sent-coach@example.com',
    ]);

    $sent = User::factory()->coach()->create([
        'forename' => 'Setup',
        'surname' => 'Sent',
        'email' => 'sent-coach@example.com',
    ]);
    $sent->issueAccountSetupToken();

    $expired = User::factory()->coach()->create([
        'forename' => 'Setup',
        'surname' => 'Expired',
        'email' => 'expired-coach@example.com',
    ]);
    $expired->issueAccountSetupToken();
    $expired->forceFill([
        'account_setup_expires_at' => now()->subDay(),
    ])->save();

    $active = User::factory()->coach()->create([
        'forename' => 'Active',
        'surname' => 'Coach',
        'email' => 'active-coach@example.com',
    ]);
    $active->forceFill([
        'account_setup_completed_at' => now()->subDay(),
    ])->save();

    $this->actingAs($admin);

    Livewire::test(CoachList::class)
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
        ->assertDontSee('Coach, Active')
        ->assertDontSee('Missing, Email');
});
