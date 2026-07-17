<?php

declare(strict_types=1);

it('references a missing dataset', function ($value) {
    expect($value)->toBeTruthy();
})->with('missing');
