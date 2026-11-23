<?php

$resolvedWhileLoading = stream_resolve_include_path('parallel-include-path.inc');

test('resolves a file through the include path while loading', function () use ($resolvedWhileLoading) {
    expect($resolvedWhileLoading)->toBeString();
});

test('requires a file through the include path while running', function () {
    expect(stream_resolve_include_path('parallel-include-path.inc'))->toBeString()
        ->and(require 'parallel-include-path.inc')->toBe('resolved through the include path');
});
