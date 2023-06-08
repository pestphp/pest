<?php

uses(Tests\CustomTestCase\CustomTestCase::class)->in('../*/');

test('closure was bound to CustomTestCase', function () {
    $this->assertCustomTrue();
});
