<?php

if (($argv[1] ?? null) !== 'start') {
    return;
}

function printLog($string = '', bool $append = false)
{
    if ($append) {
        echo " $string";

        return;
    }

    echo PHP_EOL . "> $string";
}

if (!function_exists('str_ends_with')) {
    function str_ends_with($str, $end): bool
    {
        return @substr_compare($str, $end, -strlen($end)) == 0;
    }
}

function process(string $path)
{
    if (!file_exists($path)) {
        exit("Directory [$path] doesn't exist");
    }

    if (!is_dir($path)) {
        exit("[$path] is not a directory");
    }

    $files = array_diff(scandir($path), ['..', '.']);

    foreach ($files as $filename) {
        $file = $path . DIRECTORY_SEPARATOR . $filename;

        if (is_dir($file)) {
            process($file);

            continue;
        }

        if (!str_ends_with($file, '.php')) {
            continue;
        }

        printLog("Processing [$filename]");

        $content = file_get_contents($file);

        $matches = [];
        preg_match("/@min.php-version[ ]*(\d\.?\d?) /", $content, $matches);

        $min_version = $matches[1] ?? null;

        if ($min_version !== null && version_compare(PHP_VERSION, $min_version) < 0) {
            printLog('-> disabled', true);
            rename($file, "$file.disabled");

            continue;
        }

        printLog('-> ok', true);
    }
}

$startingPath = __DIR__ . '/..';
process($startingPath);
