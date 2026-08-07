<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Calculator.php';
require_once __DIR__.'/../app/Greeter.php';

// Stands in for the built-in Laravel/Livewire watch defaults, which are not
// applicable here because the fixture installs neither package. Without the
// generated-view mapping, every changed Blade file lands on this rule and
// invalidates the whole suite — which is exactly what issue #1808 reported.
pest()->tia()->watch(['resources/views/**' => 'tests']);
