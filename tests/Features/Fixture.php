<?php

declare(strict_types=1);

it('may return a file path', function (): void {
    $file = fixture('phpunit-in-isolation.xml');

    expect($file)->toBeString()
        ->toBeFile();
});

it('may throw an exception if the file does not exist', function (): void {
    fixture('file-that-does-not-exist.php');
})->throws(InvalidArgumentException::class);
