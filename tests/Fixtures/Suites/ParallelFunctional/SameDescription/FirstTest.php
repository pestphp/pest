<?php

it('shares a description', function (int $value) {
    expect($value)->toBeInt();
})->with(['only first' => 1, 'shared' => 2]);
