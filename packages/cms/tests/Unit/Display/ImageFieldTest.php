<?php

use Coda\Cms\Display\DisplayFields\Image;

it('creates an image field with a source attribute', function () {
    $field = Image::make('coverUrl');

    expect($field->field)->toBe('coverUrl');
    expect($field->type)->toBe('image');
});

it('defaults aspect to square and fit to cover', function () {
    $field = Image::make('coverUrl');

    expect($field->aspect)->toBe('square');
    expect($field->fit)->toBe('cover');
});

it('allows aspect and fit to be customised fluently', function () {
    $field = Image::make('coverUrl')->aspect('video')->fit('contain');

    expect($field->aspect)->toBe('video');
    expect($field->fit)->toBe('contain');
});

it('returns the raw value from formatValue', function () {
    $field = Image::make('coverUrl');

    expect($field->formatValue('https://example.com/a.jpg'))->toBe('https://example.com/a.jpg');
    expect($field->formatValue(null))->toBe('');
});
