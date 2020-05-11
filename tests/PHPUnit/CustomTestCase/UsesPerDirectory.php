<?php

uses(Tests\CustomTestCase\CustomTestCase::class)->in(__DIR__);

test('closure was bound to CustomTestCase', function () {
    $this->assertCustomTrue();
});
