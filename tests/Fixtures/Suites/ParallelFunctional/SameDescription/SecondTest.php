<?php

it('shares a description', function (int $value) {
    expect($value)->toBeInt()->toBeGreaterThan(2);
})->with(['only second' => 3, 'shared' => 4]);
