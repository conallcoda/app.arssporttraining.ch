<?php

use Coda\Cms\Display\DisplayFields\Image;
use Coda\Cms\Display\DisplayFields\Text;
use Coda\Cms\Display\Table;

it('stores card fields passed to cards()', function () {
    $table = Table::make()->cards([
        Image::make('coverUrl'),
        Text::make('name'),
    ]);

    $cards = $table->getCards();

    expect($cards)->toHaveCount(2);
    expect($cards[0])->toBeInstanceOf(Image::class);
    expect($cards[1])->toBeInstanceOf(Text::class);
});

it('returns no cards when cards() has not been called', function () {
    $table = Table::make()->columns([
        Text::make('name'),
        Text::make('status'),
    ]);

    expect($table->getCards())->toBe([]);
    expect($table->hasCards())->toBeFalse();
});

it('reports hasCards as true once cards() is declared', function () {
    $table = Table::make()->cards([Text::make('name')]);

    expect($table->hasCards())->toBeTrue();
});

it('prefers card fields over columns when both are declared', function () {
    $table = Table::make()
        ->columns([Text::make('name')])
        ->cards([Text::make('slug')]);

    $cards = $table->getCards();

    expect($cards)->toHaveCount(1);
    expect($cards[0]->field)->toBe('slug');
});

it('defaults the view mode to table', function () {
    expect(Table::make()->getDefaultView())->toBe('table');
});

it('allows the default view mode to be set', function () {
    expect(Table::make()->defaultView('cards')->getDefaultView())->toBe('cards');
});

it('returns a default classic pagination when none is configured', function () {
    $pagination = Table::make()->getPagination();

    expect($pagination)->toBeInstanceOf(\Coda\Cms\Display\Pagination\Classic::class);
    expect($pagination->getPerPage())->toBe(10);
});

it('uses the table limit as the default pagination per-page', function () {
    $pagination = Table::make()->limit(25)->getPagination();

    expect($pagination->getPerPage())->toBe(25);
});

it('stores a configured pagination strategy', function () {
    $configured = \Coda\Cms\Display\Pagination::infinite()->perPage(30);
    $table = Table::make()->pagination($configured);

    expect($table->getPagination())->toBe($configured);
    expect($table->getPagination()->getPerPage())->toBe(30);
});

it('defaults cardLayout to grid', function () {
    $table = Table::make()->cards([Text::make('name')]);
    expect($table->getCardLayout())->toBe('grid');
});

it('allows cardLayout to be set to masonry', function () {
    $table = Table::make()->cards([Text::make('name')])->cardLayout('masonry');
    expect($table->getCardLayout())->toBe('masonry');
});

it('defaults cardWidth to 260', function () {
    $table = Table::make()->cards([Text::make('name')]);
    expect($table->getCardWidth())->toBe(260);
});

it('allows cardWidth to be overridden', function () {
    $table = Table::make()->cards([Text::make('name')])->cardWidth(220);
    expect($table->getCardWidth())->toBe(220);
});

it('defaults cardTitleField to null', function () {
    $table = Table::make()->cards([Text::make('name')]);
    expect($table->getCardTitleField())->toBeNull();
});

it('allows cardTitleField to be overridden', function () {
    $table = Table::make()->cards([Text::make('name')])->cardTitleField('slug');
    expect($table->getCardTitleField())->toBe('slug');
});

it('defaults cardView and masonryOverlayView to null', function () {
    $table = Table::make()->cards([Text::make('name')]);
    expect($table->getCardView())->toBeNull();
    expect($table->getMasonryOverlayView())->toBeNull();
});

it('allows cardView and masonryOverlayView to be overridden', function () {
    $table = Table::make()
        ->cards([Text::make('name')])
        ->cardView('my.card')
        ->masonryOverlayView('my.overlay');
    expect($table->getCardView())->toBe('my.card');
    expect($table->getMasonryOverlayView())->toBe('my.overlay');
});
