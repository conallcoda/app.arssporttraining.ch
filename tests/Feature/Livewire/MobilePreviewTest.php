<?php

use App\Models\Users\User;
use Coda\Cms\Livewire\MobilePreview;
use Livewire\Livewire;

it('renders when mobile preview is enabled', function () {
    config()->set('cms.mobile_preview', true);

    $user = User::factory()->admin()->create();

    Livewire::actingAs($user)
        ->test(MobilePreview::class)
        ->assertSuccessful()
        ->assertSee('Back to site');
});

it('aborts with 403 when mobile preview is disabled', function () {
    config()->set('cms.mobile_preview', false);

    $user = User::factory()->admin()->create();

    Livewire::actingAs($user)
        ->test(MobilePreview::class)
        ->assertForbidden();
});

it('defaults url to root when not provided', function () {
    config()->set('cms.mobile_preview', true);

    $user = User::factory()->admin()->create();

    Livewire::actingAs($user)
        ->test(MobilePreview::class)
        ->assertSet('url', '/');
});

it('requires authentication', function () {
    config()->set('cms.mobile_preview', true);

    $this->get('/dev/mobile-preview?url=/dashboard')
        ->assertRedirect('/login');
});

it('loads the preview page when enabled', function () {
    config()->set('cms.mobile_preview', true);

    $user = User::factory()->admin()->create();

    $this->actingAs($user)
        ->get('/dev/mobile-preview?url=/dashboard')
        ->assertOk()
        ->assertSeeLivewire(MobilePreview::class);
});
