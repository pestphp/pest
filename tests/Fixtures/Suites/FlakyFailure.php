<?php

it('fails after exhausting all retries', function () {
    throw new Exception('Always fails');
})->flaky(tries: 2);
