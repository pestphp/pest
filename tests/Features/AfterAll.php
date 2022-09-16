<?php

$file = __DIR__.DIRECTORY_SEPARATOR.'after-all-test';

beforeAll(function () use ($file) {
    @unlink($file);
});

afterAll(function () use ($file) {
    @unlink($file);
});

test('deletes file after all', function () use ($file) {
    file_put_contents($file, 'foo');
    $this->assertFileExists($file);
    register_shutdown_function(function () {
        // $this->assertFileDoesNotExist($file);
    });
});
