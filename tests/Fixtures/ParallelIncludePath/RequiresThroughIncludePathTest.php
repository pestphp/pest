<?php

$valueRequiredWhileLoading = require 'parallel-include-path.inc';

test('requires a file through the include path while loading', function () use ($valueRequiredWhileLoading) {
    expect($valueRequiredWhileLoading)->toBe('resolved through the include path');
});

test('requires a file through the include path while running', function () {
    expect(require 'parallel-include-path.inc')->toBe('resolved through the include path');
});
