<?php

use App\Models\Users\User;

it('allows coaches to access admin routes', function () {
    $coach = User::factory()->coach()->create();

    $this->actingAs($coach)
        ->get('/admin/coaches')
        ->assertSuccessful();

    $this->actingAs($coach)
        ->get('/admin/owners')
        ->assertSuccessful();
});

it('allows admins to access admin routes', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/coaches')
        ->assertSuccessful();
});

it('blocks athletes from accessing admin routes', function () {
    $athlete = User::factory()->athlete()->create();

    $this->actingAs($athlete)
        ->get('/admin/coaches')
        ->assertForbidden();
});

it('allows all types when admin_user_types is null', function () {
    config()->set('cms.admin_user_types', null);

    $athlete = User::factory()->athlete()->create();

    $this->actingAs($athlete)
        ->get('/admin/coaches')
        ->assertSuccessful();
});
