<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    File::delete(storage_path('logs/client-js.log'));
});

it('accepts client side javascript logs when enabled', function () {
    config()->set('logging.client_js.enabled', true);

    $this->postJson('/client-js-log', [
        'entries' => [
            [
                'type' => 'console.error',
                'payload' => ['Example client error'],
                'at' => now()->toISOString(),
            ],
        ],
        'meta' => [
            'url' => 'https://example.test/dashboard',
            'userAgent' => 'Test Browser',
        ],
    ], [
        'Origin' => 'http://localhost',
        'Host' => 'localhost',
    ])->assertNoContent();

    expect(File::get(storage_path('logs/client-js.log')))
        ->toContain('console.error')
        ->toContain('Example client error');
});

it('hides the client side javascript log endpoint when disabled', function () {
    config()->set('logging.client_js.enabled', false);

    $this->postJson('/client-js-log', [])->assertNotFound();
});
