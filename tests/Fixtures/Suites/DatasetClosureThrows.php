<?php

dataset('throws', function () {
    throw new RuntimeException('boom from dataset');
});

it('x', function ($a) {
    expect($a)->toBeTrue();
})->with('throws');
