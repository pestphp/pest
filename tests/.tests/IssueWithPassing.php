<?php

declare(strict_types=1);

it('passes normally', function () {
    expect(true)->toBeTrue();
});

it('references a missing dataset', function ($value) {
    expect($value)->toBeTruthy();
})->with('missing');
