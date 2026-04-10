<?php

use Pest\Arch\Exceptions\ArchExpectationFailedException;

test('pass')
    ->expect('Tests\Fixtures\Arch\ToBeCasedCorrectly\CorrectCasing')
    ->toBeCasedCorrectly();

test('failure')
    ->expect('Tests\Fixtures\Arch\ToBeCasedCorrectly\IncorrectCasing')
    ->toBeCasedCorrectly()
    ->throws(ArchExpectationFailedException::class);
