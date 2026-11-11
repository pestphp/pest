<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Calculator.php';
require_once __DIR__.'/../app/Greeter.php';

pest()->tia()->watch(['resources/views/**' => 'tests']);
