<?php

use Coda\ExpressionKit\SortExpressionCompiler;

it('compiles concat expressions into sql fragments', function () {
    $compiler = new SortExpressionCompiler;

    expect($compiler->compile('surname.concat("forename")'))
        ->toBe("CONCAT(surname, ' ', forename)");
});

it('compiles relationship-style property expressions down to the selected column names', function () {
    $compiler = new SortExpressionCompiler;

    expect($compiler->compile('owner.surname.concat("forename")'))
        ->toBe("CONCAT(surname, ' ', forename)");
});

it('splits comma separated sort segments without breaking nested method calls', function () {
    $compiler = new SortExpressionCompiler;

    expect($compiler->splitSegments('category.name, owner.surname.concat("forename"), surname'))
        ->toBe([
            'category.name',
            ' owner.surname.concat("forename")',
            ' surname',
        ]);
});
