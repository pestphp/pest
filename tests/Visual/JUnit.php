<?php

use Symfony\Component\Process\Process;

$run = function () {
    $junitLogFile = tempnam(sys_get_temp_dir(), 'junit');

    $process = new Process(
        array_merge(['php', 'bin/pest', '--log-junit', $junitLogFile], func_get_args()),
        dirname(__DIR__, 2),
        ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true'],
    );

    $process->run();

    $rawXmlContent = file_get_contents($junitLogFile);
    unlink($junitLogFile);

    // convert xml to array
    try {
        $xml = new SimpleXMLElement(preg_replace("/(<\/?)(\w+):([^>]*>)/", '$1$2$3', $rawXmlContent));

        return json_decode(json_encode((array) $xml), true);
    } catch (Exception $exception) {
        throw new XmlParseException($exception->getMessage(), $exception->getCode(), $exception->getPrevious());
    }
};

$normalizedPath = function (string $path) {
    return str_replace('/', DIRECTORY_SEPARATOR, $path);
};

test('junit output', function () use ($normalizedPath, $run) {
    $result = $run('tests/.tests/SuccessOnly.php');

    expect($result['testsuite']['@attributes'])
        ->name->toBe('Tests\tests\SuccessOnly')
        ->file->toBe($normalizedPath('tests/.tests/SuccessOnly.php'))
        ->tests->toBe('3')
        ->assertions->toBe('3')
        ->errors->toBe('0')
        ->failures->toBe('0')
        ->skipped->toBe('0');

    expect($result['testsuite']['testcase'])
        ->toHaveCount(2);

    expect($result['testsuite']['testcase'][0]['@attributes'])
        ->name->toBe('it can pass with comparison')
        ->file->toBe($normalizedPath('tests/.tests/SuccessOnly.php::it can pass with comparison'))
        ->class->toBe('Tests\tests\SuccessOnly')
        ->classname->toBe('Tests.tests.SuccessOnly')
        ->assertions->toBe('1')
        ->time->toStartWith('0.0');
});

test('junit with parallel', function () use ($normalizedPath, $run) {
    $result = $run('tests/.tests/SuccessOnly.php', '--parallel', '--processes=1', '--filter', 'can pass with comparison');

    expect($result['testsuite']['@attributes'])
        ->name->toBe('Tests\tests\SuccessOnly')
        ->file->toBe($normalizedPath('tests/.tests/SuccessOnly.php'))
        ->tests->toBe('2')
        ->assertions->toBe('2')
        ->errors->toBe('0')
        ->failures->toBe('0')
        ->skipped->toBe('0');

    expect($result['testsuite']['testcase'])
        ->toHaveCount(2);

    expect($result['testsuite']['testcase'][0]['@attributes'])
        ->name->toBe('it can pass with comparison')
        ->file->toBe($normalizedPath('tests/.tests/SuccessOnly.php::it can pass with comparison'))
        ->class->toBe('Tests\tests\SuccessOnly')
        ->classname->toBe('Tests.tests.SuccessOnly')
        ->assertions->toBe('1')
        ->time->toStartWith('0.0');
})->skip('Not working yet');
