<?php

it('can run a CMS package feature test', function () {
    expect(config('cms.home'))->toBeString();
});
