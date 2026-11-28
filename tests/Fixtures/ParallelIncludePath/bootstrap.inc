<?php

require_once __DIR__.'/../../../vendor/autoload.php';

if ((require 'parallel-include-path.inc') !== 'resolved through the include path') {
    throw new RuntimeException('The bootstrap file did not resolve the command line include path first.');
}
