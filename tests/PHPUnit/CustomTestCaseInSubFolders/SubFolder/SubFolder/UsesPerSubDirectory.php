<?php

use Pest\TestSuite;

test('closure was bound to CustomTestCase', function () {
    $this->assertCustomInSubFolderTrue();
})->skip(TestSuite::getInstance()->isInParallel, 'Nested Pest.php files are not loaded in parallel.');
