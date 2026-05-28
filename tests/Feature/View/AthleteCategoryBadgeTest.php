<?php

use Coda\Cms\Support\ColorPalette;

it('uses the shared palette rules for named category colors', function () {
    $expectedStyle = ColorPalette::solid('yellow');

    $this->blade('<x-athlete.category-badge label="Games" color="yellow" />')
        ->assertSee('Games')
        ->assertSee($expectedStyle, false)
        ->assertDontSee('background-color: yellow; color: #ffffff;', false);
});

it('still supports custom hex colors', function () {
    $this->blade('<x-athlete.category-badge label="Custom" color="#123456" />')
        ->assertSee('Custom')
        ->assertSee('background-color: #123456; color: #ffffff;', false);
});
