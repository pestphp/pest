<?php

test('depends on test in an an external file', function (string $first, string $second) {
    expect($first)->toBe('first');
    expect($second)->toBe('second');
})->dependsExternal('Tests\Features\Depends', 'first', 'second');
