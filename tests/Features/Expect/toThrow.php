<?php

use PHPUnit\Framework\ExpectationFailedException;

class CustomException extends Exception {}

test('passes', function (): void {
    expect(function (): void {
        throw new RuntimeException;
    })->toThrow(RuntimeException::class)
        ->toThrow(Exception::class)
        ->toThrow(function (RuntimeException $e): void {})
        ->and(function (): void {
            throw new RuntimeException('actual message');
        })->toThrow(function (Exception $e): void {
            expect($e->getMessage())->toBe('actual message');
        })
        ->and(function (): void {})->not->toThrow(Exception::class)
        ->and(function (): void {
            throw new RuntimeException('actual message');
        })->toThrow('actual message')
        ->and(function (): void {
            throw new Exception;
        })->not->toThrow(RuntimeException::class)
        ->and(function (): void {
            throw new RuntimeException('actual message');
        })->toThrow(RuntimeException::class, 'actual message')
        ->toThrow(function (RuntimeException $e): void {}, 'actual message')
        ->and(function (): void {
            throw new CustomException('foo');
        })->toThrow(new CustomException('foo'));
});

test('failures 1', function (): void {
    expect(function (): void {})->toThrow(RuntimeException::class);
})->throws(ExpectationFailedException::class, 'Exception ['.RuntimeException::class.'] not thrown.');

test('failures 2', function (): void {
    expect(function (): void {})->toThrow(function (RuntimeException $e): void {});
})->throws(ExpectationFailedException::class, 'Exception ['.RuntimeException::class.'] not thrown.');

test('failures 3', function (): void {
    expect(function (): void {
        throw new Exception;
    })->toThrow(function (RuntimeException $e): void {
        //
    });
})->throws(ExpectationFailedException::class, 'Failed asserting that an instance of class Exception is an instance of class RuntimeException.');

test('failures 4', function (): void {
    expect(function (): void {
        throw new Exception('actual message');
    })
        ->toThrow(function (Exception $e): void {
            expect($e->getMessage())->toBe('expected message');
        });
})->throws(ExpectationFailedException::class, 'Failed asserting that two strings are identical');

test('failures 5', function (): void {
    expect(function (): void {
        throw new Exception('actual message');
    })->toThrow('expected message');
})->throws(ExpectationFailedException::class, 'Failed asserting that \'actual message\' [ASCII](length: 14) contains "expected message" [ASCII](length: 16).');

test('failures 6', function (): void {
    expect(function (): void {})->toThrow('actual message');
})->throws(ExpectationFailedException::class, 'Exception with message [actual message] not thrown');

test('failures 7', function (): void {
    expect(function (): void {
        throw new RuntimeException('actual message');
    })->toThrow(RuntimeException::class, 'expected message');
})->throws(ExpectationFailedException::class);

test('failures 8', function (): void {
    expect(function (): void {
        throw new CustomException('actual message');
    })->toThrow(new CustomException('expected message'));
})->throws(ExpectationFailedException::class);

test('failures with custom message', function (): void {
    expect(function (): void {
        throw new RuntimeException('actual message');
    })->toThrow(RuntimeException::class, 'expected message', 'oh no!');
})->throws(ExpectationFailedException::class, 'oh no!');

test('not failures', function (): void {
    expect(function (): void {
        throw new RuntimeException;
    })->not->toThrow(RuntimeException::class);
})->throws(ExpectationFailedException::class);

test('closure missing parameter', function (): void {
    expect(function (): void {})->toThrow(function (): void {});
})->throws(InvalidArgumentException::class, 'The given closure must have a single parameter type-hinted as the class string.');

test('closure missing type-hint', function (): void {
    expect(function (): void {})->toThrow(function ($e): void {});
})->throws(InvalidArgumentException::class, 'The given closure\'s parameter must be type-hinted as the class string.');

it('can handle a non-defined exception', function (): void {
    expect(function (): void {
        throw new NonExistingException;
    })->toThrow(NonExistingException::class);
})->throws(Error::class, 'Class "NonExistingException" not found');

it('can handle a class not found Error', function (): void {
    expect(function (): void {
        throw new NonExistingException;
    })->toThrow('Class "NonExistingException" not found')
        ->toThrow(Error::class, 'Class "NonExistingException" not found');
});
