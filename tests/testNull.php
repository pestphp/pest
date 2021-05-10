<?php
/**
 * NOTE:
 * Please adjust the path '/var/www/file_store' as per your system preference.
 */

//this test will pass
test('Opening a dba connection, handle should return a resource', function () {
    $handle = dba_open('/var/www/file_store', 'n', 'flatfile');
    expect($handle)->toBeResource();
});

//this test would fail with message:
//Failed asserting that NULL is null.
test('Closing the connection, handle should be a null resource', function () {
    $handle = dba_open('/var/www/file_store', 'n', 'flatfile');
    dba_close($handle);
    expect($handle)->toBeNull();
});
