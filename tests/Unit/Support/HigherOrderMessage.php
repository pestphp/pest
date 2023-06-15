<?php

use Pest\Support\HigherOrderMessage;

test('undefined method exceptions', function () {
    $message = new HigherOrderMessage(
        __FILE__,
        1,
        'foqwdqwd',
        []
    );

    expect(fn () => $message->call($this))->toThrow(function (ReflectionException $exception) {
        expect($exception)
            ->getMessage()->toBe('Call to undefined method PHPUnit\Framework\TestCase::foqwdqwd()')
            ->getLine()->toBe(1)
            ->getFile()->toBe(__FILE__);
    });
});
