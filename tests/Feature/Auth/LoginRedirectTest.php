<?php

use App\Models\Users\User;
use Coda\Cms\Http\Responses\TypeAwareLoginResponse;
use Illuminate\Http\Request;

it('redirects coaches to admin programs after login', function () {
    $coach = User::factory()->coach()->create();

    $this->actingAs($coach);

    $response = app(TypeAwareLoginResponse::class)
        ->toResponse(Request::create('/login', 'POST'));

    expect($response->getTargetUrl())->toContain('/admin/programs');
});

it('redirects athletes to dashboard after login', function () {
    $athlete = User::factory()->athlete()->create();

    $this->actingAs($athlete);

    $response = app(TypeAwareLoginResponse::class)
        ->toResponse(Request::create('/login', 'POST'));

    expect($response->getTargetUrl())->toContain('/dashboard');
});

it('redirects admins to admin programs after login', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    $response = app(TypeAwareLoginResponse::class)
        ->toResponse(Request::create('/login', 'POST'));

    expect($response->getTargetUrl())->toContain('/admin/programs');
});

it('falls back to cms.home when home_by_type is null', function () {
    config()->set('cms.home_by_type', null);

    $athlete = User::factory()->athlete()->create();

    $this->actingAs($athlete);

    $response = app(TypeAwareLoginResponse::class)
        ->toResponse(Request::create('/login', 'POST'));

    expect($response->getTargetUrl())->toContain(config('fortify.home'));
});
