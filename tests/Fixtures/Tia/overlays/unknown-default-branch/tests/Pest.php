<?php

declare(strict_types=1);

require_once __DIR__.'/../app/Calculator.php';
require_once __DIR__.'/../app/Greeter.php';

// A branch the repository does not have. Accepted as configured — a name that
// resolves to no baseline degrades to a full run, which is safe.
pest()->tia()->defaultBranch('nope');
