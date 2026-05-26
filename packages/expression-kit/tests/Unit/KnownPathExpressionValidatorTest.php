<?php

use Coda\ExpressionKit\KnownPathExpressionValidator;

it('accepts expressions using known roots and nested paths', function () {
    $validator = new KnownPathExpressionValidator;

    $result = $validator->validate(
        'attendee.profile.interest_topics.count < 3 or not attendee.biography.present',
        [
            'attendee',
            'attendee.profile',
            'attendee.profile.interest_topics.count',
            'attendee.biography.present',
        ],
    );

    expect($result->isValid())->toBeTrue();
});

it('reports syntax errors', function () {
    $validator = new KnownPathExpressionValidator;

    $result = $validator->validate('attendee..broken', ['attendee']);

    expect($result->syntaxError)->not->toBeNull();
});

it('reports unknown paths', function () {
    $validator = new KnownPathExpressionValidator;

    $result = $validator->validate(
        'attendee.profile.interest_topics.count < 3 and attendee.location.country_code == "DE"',
        [
            'attendee',
            'attendee.profile',
            'attendee.profile.interest_topics.count',
        ],
    );

    expect($result->unknownPaths)->toBe(['attendee.location.country_code']);
});
