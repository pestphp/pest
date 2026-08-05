<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Calculator.php';
require_once __DIR__.'/../app/Greeter.php';

// Declared default branch. Wins over whatever the repository autodetects — the
// escape hatch for a checkout with no `origin/HEAD` and a misleading
// `init.defaultBranch`.
pest()->tia()->defaultBranch('master');
