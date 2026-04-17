<?php

use App\Models\Users\User;
use Coda\Cms\Livewire\LoginUserSwitcher;
use Livewire\Livewire;

it('renders when login test switching is enabled', function () {
    config()->set('cms.user_switching', true);

    User::factory()->athlete()->create();

    Livewire::test(LoginUserSwitcher::class)
        ->assertSuccessful()
        ->assertSee('Test Login')
        ->assertSee('Athlete');
});

it('logs in as the selected user from the login page', function () {
    config()->set('cms.user_switching', true);
    config()->set('cms.home_by_type', [
        'athlete' => '/dashboard',
        '*' => '/admin/programs',
    ]);

    $athlete = User::factory()->athlete()->create();

    Livewire::test(LoginUserSwitcher::class)
        ->set('selectedType', 'athlete')
        ->set('selectedUserId', (string) $athlete->id)
        ->call('loginAsSelectedUser')
        ->assertRedirect('/dashboard');

    expect(auth()->id())->toBe($athlete->id);
});
