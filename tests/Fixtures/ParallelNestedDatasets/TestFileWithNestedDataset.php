<?php

declare(strict_types=1);

test('loads nested dataset', function (string $name): void {
    expect($name)->not->toBeEmpty();
})->with('nested.users');
