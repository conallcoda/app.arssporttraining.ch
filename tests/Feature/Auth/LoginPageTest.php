<?php

use App\Models\Users\User;
use Coda\Cms\Livewire\LoginUserSwitcher;
use Livewire\Livewire;

it('shows only the regular login form even when admin user switching is enabled', function () {
    config()->set('cms.user_switching', true);
    config()->set('athlete.user_switching', false);

    $this->get('/login')
        ->assertOk()
        ->assertSee('Login')
        ->assertDontSee('Test Login')
        ->assertDontSee('Test');
});

it('shows only the regular login form when login test switching is disabled', function () {
    config()->set('cms.user_switching', false);
    config()->set('athlete.user_switching', false);

    $this->get('/login')
        ->assertOk()
        ->assertSee('Login')
        ->assertDontSee('Test Login');
});

it('shows normal and test login tabs when athlete user switching is enabled', function () {
    config()->set('cms.user_switching', false);
    config()->set('athlete.user_switching', true);

    $this->get('/login')
        ->assertOk()
        ->assertSee('Normal')
        ->assertSee('Test')
        ->assertSee('Test Login');
});

it('allows test login only when athlete user switching is enabled', function () {
    config()->set('athlete.user_switching', true);
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

it('forbids the test login component when athlete user switching is disabled', function () {
    config()->set('athlete.user_switching', false);

    Livewire::test(LoginUserSwitcher::class)
        ->assertForbidden();
});
