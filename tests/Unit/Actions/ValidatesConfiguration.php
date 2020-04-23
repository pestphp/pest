<?php

use Pest\Actions\ValidatesConfiguration;
use Pest\Exceptions\AttributeNotSupportedYet;

it('throws exception when `process isolation` is true', function () {
    $this->expectException(AttributeNotSupportedYet::class);
    $this->expectExceptionMessage('The PHPUnit attribute `processIsolation` with value `true` is not supported yet.');

    /* @var \PHPUnit\Framework\MockObject\MockObject $configuration */
    ValidatesConfiguration::in(new class() {
        public function processIsolation()
        {
            return true;
        }
    });
});

it('do not throws exception when `process isolation` is false', function () {
    ValidatesConfiguration::in(new class() {
        public function processIsolation()
        {
            return false;
        }
    });

    assertTrue(true);
});
