<?php

use App\Models\Users\User;

it('does not auto-login when admin auto login is false', function () {
    config()->set('cms.auto_login', false);

    $this->get('/login')
        ->assertOk();

    expect(auth()->check())->toBeFalse();
});

it('auto-logins as the configured admin auto login email in non-production', function () {
    config()->set('cms.auto_login', 'coach@example.com');
    config()->set('cms.home_by_type', [
        'athlete' => '/dashboard',
        '*' => '/admin/programs',
    ]);

    $coach = User::factory()->coach()->create([
        'email' => 'coach@example.com',
    ]);

    $this->get('/login')
        ->assertRedirect('/admin/programs');

    expect(auth()->id())->toBe($coach->id);
});
