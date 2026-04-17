<?php

it('shows normal and test tabs on the login page when login test switching is enabled', function () {
    config()->set('cms.user_switching', true);

    $this->get('/login')
        ->assertOk()
        ->assertSee('Normal')
        ->assertSee('Test');
});

it('shows only the regular login form when login test switching is disabled', function () {
    config()->set('cms.user_switching', false);

    $this->get('/login')
        ->assertOk()
        ->assertSee('Login')
        ->assertDontSee('Test Login');
});
