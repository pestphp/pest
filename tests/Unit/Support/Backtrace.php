<?php

use Pest\Support\Backtrace;

it('gets file name from called file', function (): void {
    $a = (fn (): string => Backtrace::file());

    expect($a())->toBe(__FILE__);
});

it('attributes the call site to this file', function (): void {
    expect(Backtrace::testFile())->toBe(__FILE__)
        ->and(backtrace_helper_calls_test_file())->toBe(__FILE__);
});

function backtrace_helper_calls_test_file(): string
{
    return Backtrace::testFile();
}
