<?php

declare(strict_types=1);

use Pest\Support\Coverage;

it('fails when project is not a git repository', function () {
    $tmp = sys_get_temp_dir().'/pest-coverage-changed-'.uniqid('', true);
    mkdir($tmp, 0777, true);

    $result = Coverage::resolveBranchChangedPhpPaths($tmp);

    expect($result['ok'])->toBeFalse()
        ->and($result['absolutePhpPaths'])->toBe([])
        ->and($result['lineSetsByNormalizedPath'])->toBe([])
        ->and($result['errorMessage'])->toContain('Git');

    rmdir($tmp);
});

it('parses unified diff -U0 into touched new-file line numbers', function () {
    $ref = new \ReflectionClass(Coverage::class);
    $method = $ref->getMethod('coverageParseUnifiedDiffZero');
    $method->setAccessible(true);

    $diff = <<<'DIFF'
diff --git a/App/Example.php b/App/Example.php
new file mode 100644
index 0000000..1111111
--- /dev/null
+++ b/App/Example.php
@@ -0,0 +1,2 @@
+<?php
+echo 1;
DIFF;

    /** @var array<string, array<int, true>> $lines */
    $lines = $method->invoke(null, $diff);

    expect($lines)->toHaveKey('App/Example.php')
        ->and($lines['App/Example.php'])->toMatchArray([1 => true, 2 => true]);
});
