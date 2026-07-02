<?php

use Coda\ExpressionKit\Projection;
use Coda\ExpressionKit\ProjectionEvaluator;
use Coda\ExpressionKit\ProjectionField;

it('projects scalar fields and nested objects from payload data', function () {
    $projection = Projection::make()
        ->field(ProjectionField::make('person.forename')->label('First Name'))
        ->field(ProjectionField::make('person.surname')->label('Last Name'))
        ->object('company', fn ($object) => $object
            ->field(ProjectionField::make('companySummary.name')->label('Name'))
            ->field(ProjectionField::make('companySummary.url')->label('URL'))
        );

    $result = (new ProjectionEvaluator)->evaluate($projection, [
        'person' => [
            'forename' => 'Ada',
            'surname' => 'Lovelace',
        ],
        'companySummary' => [
            'name' => 'Analytical Engines Ltd',
            'url' => 'https://example.com',
        ],
    ]);

    expect($result)->toBe([
        'forename' => 'Ada',
        'surname' => 'Lovelace',
        'company' => [
            'name' => 'Analytical Engines Ltd',
            'url' => 'https://example.com',
        ],
    ]);
});

it('returns null for missing projection paths', function () {
    $projection = Projection::make()
        ->field('person.forename')
        ->object('company', fn ($object) => $object
            ->field('companySummary.logoUrl')
        );

    $result = (new ProjectionEvaluator)->evaluate($projection, [
        'person' => [],
    ]);

    expect($result)->toBe([
        'forename' => null,
        'company' => [
            'logoUrl' => null,
        ],
    ]);
});
