<?php

declare(strict_types=1);

use Pest\PendingCalls\TestCall;
use Pest\TestSuite;

it('throws exception if no class nor method has been found', function (): void {
    $testCall = new TestCall(TestSuite::getInstance(), 'filename', 'description', fn () => 'closure');

    $testCall->covers('fakeName');
})->throws(InvalidArgumentException::class, 'No class, trait or method named [fakeName] has been found.');
