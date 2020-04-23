<?php

$file = __DIR__ . DIRECTORY_SEPARATOR . 'after-all-test';

afterAll(fn () => unlink($file));

test('deletes file after all', function () use ($file) {
    file_put_contents($file, 'foo');
    assertFileExists($file);
    register_shutdown_function(fn () => assertFileNotExists($file));
});
