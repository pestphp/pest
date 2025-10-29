<?php

it('succeeds after a few attempts', function () {
    $path = sys_get_temp_dir().'/pest_attempt.txt';
    $attempts = 1;
    if (file_exists($path)) {
        $attempts = (int) file_get_contents($path);
    } else {
        file_put_contents($path, "$attempts");
    }
    file_put_contents($path, (string) ($attempts + 1));
    expect($attempts)->toEqual(2);
    if ($attempts == 2) {
        unlink($path);
    }
})->attempts(2);
