<?php

declare(strict_types=1);

// The fixture project has no composer autoloader of its own — its `vendor/`
// holds nothing but a shim pointing back at Pest's. Requiring the two classes
// here is enough for every test file in the suite.
require_once __DIR__.'/../app/Calculator.php';
require_once __DIR__.'/../app/Greeter.php';
