<?php

pest()->afterAll(function () {
    $marker = getenv('PEST_AFTERALL_MARKER');

    if (is_string($marker) && $marker !== '') {
        file_put_contents($marker, '1');
    }
});

test('first', fn () => expect(true)->toBeTrue());

test('second', fn () => expect(true)->toBeTrue());
