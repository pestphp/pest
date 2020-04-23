<?php

declare(strict_types=1);

$globalsFilePath = implode(DIRECTORY_SEPARATOR, [
    dirname(__DIR__),
    'vendor',
    'phpunit',
    'phpunit',
    'src',
    'Framework',
    'Assert',
    'Functions.php',
]);

$compiledFilePath = implode(DIRECTORY_SEPARATOR, [dirname(__DIR__), 'compiled', 'globals.php']);

@unlink($compiledFilePath);


$replace = fn($contents, $string, $by) => str_replace($string, $by, $contents);
$remove = fn($contents, $string, $by) => str_replace($string, '', $contents);

$contents = file_get_contents($globalsFilePath);
$contents = $replace($contents, 'namespace PHPUnit\Framework;', 'use PHPUnit\Framework\Assert;');
$contents = $remove($contents, 'use ArrayAccess;', '');
$contents = $remove($contents, 'use Countable;', '');
$contents = $remove($contents, 'use DOMDocument;', '');
$contents = $remove($contents, 'use DOMElement;', '');
$contents = $remove($contents, 'use Throwable;', '');

file_put_contents(implode(DIRECTORY_SEPARATOR, [dirname(__DIR__), 'compiled', 'globals.php']), $contents);