<?php

use App\Support\Ui\CategoryColorStyle;
use Coda\Cms\Support\ColorPalette;

it('resolves named palette colors through the shared cms palette', function () {
    expect(CategoryColorStyle::resolve('yellow'))
        ->toBe(ColorPalette::solid('yellow'));
});

it('resolves hex colors with readable text contrast', function () {
    expect(CategoryColorStyle::resolve('#123456'))
        ->toBe('background-color: #123456; color: #ffffff;')
        ->and(CategoryColorStyle::resolve('#ffee00'))
        ->toBe('background-color: #ffee00; color: #111827;');
});

it('rejects invalid color input', function () {
    expect(CategoryColorStyle::resolve(''))->toBeNull()
        ->and(CategoryColorStyle::resolve('"><script>'))->toBeNull();
});
