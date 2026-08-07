<?php

use Symfony\Component\Process\Process;

$run = function () {
    $junitLogFile = tempnam(sys_get_temp_dir(), 'junit');

    $process = new Process(
        array_merge(['php', 'bin/pest', '--log-junit', $junitLogFile], func_get_args()),
        dirname(__DIR__, 2),
        ['COLLISION_PRINTER' => 'DefaultPrinter', 'COLLISION_IGNORE_DURATION' => 'true', 'PAO_DISABLE' => '1'],
    );

    $process->run();

    $rawXmlContent = file_get_contents($junitLogFile);
    unlink($junitLogFile);

    try {
        $xml = new SimpleXMLElement(preg_replace("/(<\/?)(\w+):([^>]*>)/", '$1$2$3', $rawXmlContent));

        return json_decode(json_encode((array) $xml), true);
    } catch (Exception $exception) {
        throw new XmlParseException($exception->getMessage(), $exception->getCode(), $exception->getPrevious());
    }
};

$normalizedPath = (fn (string $path): string => str_replace('/', DIRECTORY_SEPARATOR, $path));

test('junit output', function () use ($normalizedPath, $run): void {
    $result = $run('tests/Fixtures/Suites/SuccessOnly.php');

    expect($result['testsuite']['@attributes'])
        ->name->toBe('Tests\Fixtures\Suites\SuccessOnly')
        ->file->toBe($normalizedPath('tests/Fixtures/Suites/SuccessOnly.php'))
        ->tests->toBe('4')
        ->assertions->toBe('4')
        ->errors->toBe('0')
        ->failures->toBe('0')
        ->skipped->toBe('0')
        ->and($result['testsuite']['testcase'])->toHaveCount(2)
        ->and($result['testsuite']['testcase'][0]['@attributes'])->name->toBe('it can pass with comparison')->file->toBe($normalizedPath('tests/Fixtures/Suites/SuccessOnly.php::it can pass with comparison'))->class->toBe('Tests\Fixtures\Suites\SuccessOnly')->classname->toBe('Tests.Fixtures.Suites.SuccessOnly')->assertions->toBe('1')->time->toStartWith('0.0');
});

test('junit with parallel', function () use ($normalizedPath, $run): void {
    $result = $run('tests/Fixtures/Suites/SuccessOnly.php', '--parallel', '--processes=1', '--filter', 'can pass with comparison');

    expect($result['testsuite']['@attributes'])
        ->name->toBe('Tests\Fixtures\Suites\SuccessOnly')
        ->file->toBe($normalizedPath('tests/Fixtures/Suites/SuccessOnly.php'))
        ->tests->toBe('1')
        ->assertions->toBe('1')
        ->errors->toBe('0')
        ->failures->toBe('0')
        ->skipped->toBe('0')
        ->and($result['testsuite']['testcase']['@attributes'])->name->toBe('it can pass with comparison')->file->toBe($normalizedPath('tests/Fixtures/Suites/SuccessOnly.php::it can pass with comparison'))->class->toBe('Tests\Fixtures\Suites\SuccessOnly')->classname->toBe('Tests.Fixtures.Suites.SuccessOnly')->assertions->toBe('1')->time->toStartWith('0.0');
});
