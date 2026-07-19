<?php

declare(strict_types=1);

$file = __DIR__.DIRECTORY_SEPARATOR.'after-all-test';

beforeAll(function () use ($file): void {
    @unlink($file);
});

afterAll(function () use ($file): void {
    @unlink($file);
});

test('deletes file after all', function () use ($file): void {
    file_put_contents($file, 'foo');
    expect($file)->toBeFile();
    register_shutdown_function(function (): void {
        // $this->assertFileDoesNotExist($file);
    });
});
