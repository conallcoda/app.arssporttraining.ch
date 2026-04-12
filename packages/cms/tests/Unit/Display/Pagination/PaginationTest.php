<?php

use Coda\Cms\Display\Pagination;
use Coda\Cms\Display\Pagination\Classic;
use Coda\Cms\Display\Pagination\Hybrid;
use Coda\Cms\Display\Pagination\Infinite;
use Coda\Cms\Display\Pagination\LoadMore;

it('constructs classic pagination via factory', function () {
    $pagination = Pagination::classic();

    expect($pagination)->toBeInstanceOf(Classic::class);
    expect($pagination->isAccumulating())->toBeFalse();
    expect($pagination->partialName())->toBe('classic');
});

it('constructs load-more pagination via factory', function () {
    $pagination = Pagination::loadMore();

    expect($pagination)->toBeInstanceOf(LoadMore::class);
    expect($pagination->isAccumulating())->toBeTrue();
    expect($pagination->partialName())->toBe('load-more');
});

it('constructs infinite pagination via factory', function () {
    $pagination = Pagination::infinite();

    expect($pagination)->toBeInstanceOf(Infinite::class);
    expect($pagination->isAccumulating())->toBeTrue();
    expect($pagination->partialName())->toBe('infinite');
});

it('constructs hybrid pagination via factory', function () {
    $pagination = Pagination::hybrid();

    expect($pagination)->toBeInstanceOf(Hybrid::class);
    expect($pagination->isAccumulating())->toBeTrue();
    expect($pagination->partialName())->toBe('hybrid');
});

it('defaults perPage to 10 across all strategies', function () {
    expect(Pagination::classic()->getPerPage())->toBe(10);
    expect(Pagination::loadMore()->getPerPage())->toBe(10);
    expect(Pagination::infinite()->getPerPage())->toBe(10);
    expect(Pagination::hybrid()->getPerPage())->toBe(10);
});

it('allows perPage to be set fluently', function () {
    $pagination = Pagination::classic()->perPage(25);

    expect($pagination->getPerPage())->toBe(25);
});

it('returns static for fluent chaining', function () {
    $pagination = Pagination::infinite()->perPage(15);

    expect($pagination)->toBeInstanceOf(Infinite::class);
    expect($pagination->getPerPage())->toBe(15);
});
