<?php

declare(strict_types=1);
/*
 * This file is part of PHPUnit.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Pest\Logging;

use DOMDocument;
use DOMElement;
use Exception;
use Pest\Concerns\Testable;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\ExceptionWrapper;
use PHPUnit\Framework\SelfDescribing;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestFailure;
use PHPUnit\Framework\TestListener;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\Warning;
use PHPUnit\Util\Filter;
use PHPUnit\Util\Printer;
use PHPUnit\Util\Xml;
use ReflectionClass;
use ReflectionException;
use Throwable;

use function class_exists;
use function get_class;
use function method_exists;
use function sprintf;
use function str_replace;
use function trim;

/**
 * @internal This class is not covered by the backward compatibility promise for PHPUnit
 */
final class JUnit extends Printer implements TestListener
{
    /**
     * @var DOMDocument
     */
    private $document;

    /**
     * @var DOMElement
     */
    private $root;

    /**
     * @var DOMElement[]
     */
    private $testSuites = [];

    /**
     * @var int[]
     */
    private $testSuiteTests = [0];

    /**
     * @var int[]
     */
    private $testSuiteAssertions = [0];

    /**
     * @var int[]
     */
    private $testSuiteErrors = [0];

    /**
     * @var int[]
     */
    private $testSuiteWarnings = [0];

    /**
     * @var int[]
     */
    private $testSuiteFailures = [0];

    /**
     * @var int[]
     */
    private $testSuiteSkipped = [0];

    /**
     * @var int[]|float[]
     */
    private $testSuiteTimes = [0];

    /**
     * @var int
     */
    private $testSuiteLevel = 0;

    /**
     * @var DOMElement|null
     */
    private $currentTestCase;

    public function __construct(string $out)
    {
        $this->document               = new DOMDocument('1.0', 'UTF-8');
        $this->document->formatOutput = true;

        $this->root = $this->document->createElement('testsuites');
        $this->document->appendChild($this->root);

        parent::__construct($out);
    }

    /**
     * Flush buffer and close output.
     */
    public function flush(): void
    {
        $this->write($this->getXML());

        parent::flush();
    }

    /**
     * An error occurred.
     */
    public function addError(Test $test, Throwable $t, float $time): void
    {
        $this->doAddFault($test, $t, 'error');
        $this->testSuiteErrors[$this->testSuiteLevel]++;
    }

    /**
     * A warning occurred.
     */
    public function addWarning(Test $test, Warning $e, float $time): void
    {
        $this->doAddFault($test, $e, 'warning');
        $this->testSuiteWarnings[$this->testSuiteLevel]++;
    }

    /**
     * A failure occurred.
     */
    public function addFailure(Test $test, AssertionFailedError $e, float $time): void
    {
        $this->doAddFault($test, $e, 'failure');
        $this->testSuiteFailures[$this->testSuiteLevel]++;
    }

    /**
     * Incomplete test.
     */
    public function addIncompleteTest(Test $test, Throwable $t, float $time): void
    {
        $this->doAddSkipped();
    }

    /**
     * Risky test.
     */
    public function addRiskyTest(Test $test, Throwable $t, float $time): void
    {
    }

    /**
     * Skipped test.
     */
    public function addSkippedTest(Test $test, Throwable $t, float $time): void
    {
        $this->doAddSkipped();
    }

    public function startTestSuite(TestSuite $suite): void
    {
        $testSuite = $this->document->createElement('testsuite');
        $testSuite->setAttribute('name', $suite->getName());

        if (class_exists($suite->getName(), false)) {
            try {
                $class = new ReflectionClass($suite->getName());

                if ($class->hasMethod('__getFileName')) {
                    $fileName = $class->getMethod('__getFileName')->invoke(null);
                } else {
                    $fileName = $class->getFileName();
                }

                $testSuite->setAttribute('file', $fileName);
            } catch (ReflectionException $e) {
                // @ignoreException
            }
        }

        if ($this->testSuiteLevel > 0) {
            $this->testSuites[$this->testSuiteLevel]->appendChild($testSuite);
        } else {
            $this->root->appendChild($testSuite);
        }

        $this->testSuiteLevel++;
        $this->testSuites[$this->testSuiteLevel]          = $testSuite;
        $this->testSuiteTests[$this->testSuiteLevel]      = 0;
        $this->testSuiteAssertions[$this->testSuiteLevel] = 0;
        $this->testSuiteErrors[$this->testSuiteLevel]     = 0;
        $this->testSuiteWarnings[$this->testSuiteLevel]   = 0;
        $this->testSuiteFailures[$this->testSuiteLevel]   = 0;
        $this->testSuiteSkipped[$this->testSuiteLevel]    = 0;
        $this->testSuiteTimes[$this->testSuiteLevel]      = 0;
    }

    public function endTestSuite(TestSuite $suite): void
    {
        $this->testSuites[$this->testSuiteLevel]->setAttribute(
            'tests',
            (string) $this->testSuiteTests[$this->testSuiteLevel]
        );

        $this->testSuites[$this->testSuiteLevel]->setAttribute(
            'assertions',
            (string) $this->testSuiteAssertions[$this->testSuiteLevel]
        );

        $this->testSuites[$this->testSuiteLevel]->setAttribute(
            'errors',
            (string) $this->testSuiteErrors[$this->testSuiteLevel]
        );

        $this->testSuites[$this->testSuiteLevel]->setAttribute(
            'warnings',
            (string) $this->testSuiteWarnings[$this->testSuiteLevel]
        );

        $this->testSuites[$this->testSuiteLevel]->setAttribute(
            'failures',
            (string) $this->testSuiteFailures[$this->testSuiteLevel]
        );

        $this->testSuites[$this->testSuiteLevel]->setAttribute(
            'skipped',
            (string) $this->testSuiteSkipped[$this->testSuiteLevel]
        );

        $this->testSuites[$this->testSuiteLevel]->setAttribute(
            'time',
            sprintf('%F', $this->testSuiteTimes[$this->testSuiteLevel])
        );

        if ($this->testSuiteLevel > 1) {
            $this->testSuiteTests[$this->testSuiteLevel - 1] += $this->testSuiteTests[$this->testSuiteLevel];
            $this->testSuiteAssertions[$this->testSuiteLevel - 1] += $this->testSuiteAssertions[$this->testSuiteLevel];
            $this->testSuiteErrors[$this->testSuiteLevel - 1] += $this->testSuiteErrors[$this->testSuiteLevel];
            $this->testSuiteWarnings[$this->testSuiteLevel - 1] += $this->testSuiteWarnings[$this->testSuiteLevel];
            $this->testSuiteFailures[$this->testSuiteLevel - 1] += $this->testSuiteFailures[$this->testSuiteLevel];
            $this->testSuiteSkipped[$this->testSuiteLevel - 1] += $this->testSuiteSkipped[$this->testSuiteLevel];
            $this->testSuiteTimes[$this->testSuiteLevel - 1] += $this->testSuiteTimes[$this->testSuiteLevel];
        }

        $this->testSuiteLevel--;
    }

    /**
     * A test started.
     *
     * @param Test|Testable $test
     */
    public function startTest(Test $test): void
    {
        $usesDataprovider = false;

        if (method_exists($test, 'usesDataProvider')) {
            $usesDataprovider = $test->usesDataProvider();
        }

        $testCase = $this->document->createElement('testcase');
        $testCase->setAttribute('name', $test->getName());

        try {
            $class = new ReflectionClass($test);
            // @codeCoverageIgnoreStart
        } catch (ReflectionException $e) {
            throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
        }
        // @codeCoverageIgnoreEnd

        $methodName = $test->getName(!$usesDataprovider);

        if ($class->hasMethod($methodName)) {
            try {
                $method = $class->getMethod($methodName);
                // @codeCoverageIgnoreStart
            } catch (ReflectionException $e) {
                // @phpstan-ignore-next-line
                throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
            }
            // @codeCoverageIgnoreEnd

            $testCase->setAttribute('class', $class->getName());
            $testCase->setAttribute('classname', str_replace('\\', '.', $class->getName()));
            $fileName = $class->getFileName();
            if ($fileName !== false) {
                $testCase->setAttribute('file', $fileName);
            }
            $testCase->setAttribute('line', (string) $method->getStartLine());
        }

        if (TeamCity::isPestTest($test)) {
            $testCase->setAttribute('class', $test->getPrintableTestCaseName());
            $testCase->setAttribute('classname', str_replace('\\', '.', $test->getPrintableTestCaseName()));
            // @phpstan-ignore-next-line
            $testCase->setAttribute('file', $test->__getFileName());
        }

        $this->currentTestCase = $testCase;
    }

    /**
     * A test ended.
     */
    public function endTest(Test $test, float $time): void
    {
        $numAssertions = 0;

        if (method_exists($test, 'getNumAssertions')) {
            $numAssertions = $test->getNumAssertions();
        }

        $this->testSuiteAssertions[$this->testSuiteLevel] += $numAssertions;

        if ($this->currentTestCase !== null) {
            $this->currentTestCase->setAttribute(
                'assertions',
                (string) $numAssertions
            );

            $this->currentTestCase->setAttribute(
                'time',
                sprintf('%F', $time)
            );

            $this->testSuites[$this->testSuiteLevel]->appendChild(
                $this->currentTestCase
            );
        }

        $this->testSuiteTests[$this->testSuiteLevel]++;
        $this->testSuiteTimes[$this->testSuiteLevel] += $time;

        $testOutput = '';

        if (method_exists($test, 'hasOutput') && method_exists($test, 'getActualOutput')) {
            $testOutput = $test->hasOutput() ? $test->getActualOutput() : '';
        }

        if ($testOutput !== '') {
            $systemOut = $this->document->createElement(
                'system-out',
                Xml::prepareString($testOutput)
            );

            if ($this->currentTestCase !== null) {
                $this->currentTestCase->appendChild($systemOut);
            }
        }

        $this->currentTestCase = null;
    }

    /**
     * Returns the XML as a string.
     */
    public function getXML(): string
    {
        $xml = $this->document->saveXML();
        if ($xml === false) {
            return '';
        }

        return $xml;
    }

    private function doAddFault(Test $test, Throwable $t, string $type): void
    {
        if ($this->currentTestCase === null) {
            return;
        }

        if ($test instanceof SelfDescribing) {
            $buffer = $test->toString() . "\n";
        } else {
            $buffer = '';
        }

        $buffer .= trim(
            TestFailure::exceptionToString($t) . "\n" .
            Filter::getFilteredStacktrace($t)
        );

        $fault = $this->document->createElement(
            $type,
            Xml::prepareString($buffer)
        );

        if ($t instanceof ExceptionWrapper) {
            $fault->setAttribute('type', $t->getClassName());
        } else {
            $fault->setAttribute('type', get_class($t));
        }

        $this->currentTestCase->appendChild($fault);
    }

    private function doAddSkipped(): void
    {
        if ($this->currentTestCase === null) {
            return;
        }

        $skipped = $this->document->createElement('skipped');

        $this->currentTestCase->appendChild($skipped);

        $this->testSuiteSkipped[$this->testSuiteLevel]++;
    }
}
