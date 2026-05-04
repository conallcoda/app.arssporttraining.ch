<?php

use App\Support\Import\ImportedEmailNormalizer;

it('nulls imported example dot com emails', function () {
    expect(ImportedEmailNormalizer::normalize('athlete@example.com'))->toBeNull()
        ->and(ImportedEmailNormalizer::normalize('ATHLETE@EXAMPLE.COM'))->toBeNull()
        ->and(ImportedEmailNormalizer::normalize(' athlete@example.com '))->toBeNull();
});

it('preserves real imported emails', function () {
    expect(ImportedEmailNormalizer::normalize('athlete@realmail.com'))->toBe('athlete@realmail.com')
        ->and(ImportedEmailNormalizer::normalize(null))->toBeNull()
        ->and(ImportedEmailNormalizer::normalize(''))->toBeNull();
});
