<?php

/*
 * BSD 3-Clause License
 *
 * Copyright (c) 2001-2023, Sebastian Bergmann
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice, this
 *    list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 *
 * 3. Neither the name of the copyright holder nor the names of its
 *    contributors may be used to endorse or promote products derived from
 *    this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

declare(strict_types=1);

namespace PHPUnit\Runner;

use Exception;
use Pest\Contracts\HasPrintableTestCaseName;
use Pest\Panic;
use Pest\TestCases\IgnorableTestCase;
use Pest\TestSuite;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use Throwable;

use function array_diff;
use function array_values;
use function basename;
use function class_exists;
use function get_declared_classes;
use function substr;

/**
 * @internal This class is not covered by the backward compatibility promise for PHPUnit
 */
final class TestSuiteLoader
{
    /**
     * @psalm-var list<class-string>
     */
    private static array $loadedClasses = [];

    /**
     * @psalm-var array<string, array<class-string>>
     */
    private static array $loadedClassesByFilename = [];

    /**
     * @psalm-var list<class-string>
     */
    private static array $declaredClasses = [];

    public function __construct()
    {
        if (empty(self::$declaredClasses)) {
            self::$declaredClasses = get_declared_classes();
        }
    }

    /**
     * @throws Exception
     */
    public function load(string $suiteClassFile): ReflectionClass
    {
        $suiteClassName = $this->classNameFromFileName($suiteClassFile);

        (static function () use ($suiteClassFile) {
            try {
                include_once $suiteClassFile;
            } catch (Throwable $e) {
                Panic::with($e);
            }

            TestSuite::getInstance()->tests->makeIfNeeded($suiteClassFile);
        })();

        $loadedClasses = array_values(
            array_diff(
                get_declared_classes(),
                array_merge(
                    self::$declaredClasses,
                    self::$loadedClasses
                )
            )
        );

        self::$loadedClasses = array_merge($loadedClasses, self::$loadedClasses);

        foreach ($loadedClasses as $loadedClass) {
            $reflection = new ReflectionClass($loadedClass);
            $filename = $reflection->getFileName();
            self::$loadedClassesByFilename[$filename] = [
                $loadedClass,
                ...self::$loadedClassesByFilename[$filename] ?? [],
            ];
        }

        $loadedClasses = array_merge(self::$loadedClassesByFilename[$suiteClassFile] ?? [], $loadedClasses);

        if (empty($loadedClasses)) {
            return $this->exceptionFor($suiteClassName, $suiteClassFile);
        }

        $testCaseFound = false;
        $class = false;

        foreach (array_reverse($loadedClasses) as $loadedClass) {
            if (
                is_subclass_of($loadedClass, HasPrintableTestCaseName::class)
                || is_subclass_of($loadedClass, TestCase::class)) {
                try {
                    $class = new ReflectionClass($loadedClass);
                    // @codeCoverageIgnoreStart
                } catch (ReflectionException) {
                    continue;
                }

                if ($class->isAbstract() || ($suiteClassFile !== $class->getFileName())) {
                    if (! str_contains($class->getFileName(), 'TestCaseFactory.php')) {
                        continue;
                    }
                }

                $suiteClassName = $loadedClass;
                $testCaseFound = true;

                break;
            }
        }

        if (! $testCaseFound) {
            foreach (array_reverse($loadedClasses) as $loadedClass) {
                $offset = 0 - strlen($suiteClassName);

                if (stripos(substr($loadedClass, $offset - 1), '\\'.$suiteClassName) === 0 ||
                    stripos(substr($loadedClass, $offset - 1), '_'.$suiteClassName) === 0) {
                    try {
                        $class = new ReflectionClass($loadedClass);
                        // @codeCoverageIgnoreStart
                    } catch (ReflectionException) {
                        continue;
                    }

                    $suiteClassName = $loadedClass;
                    $testCaseFound = true;

                    break;
                }
            }
        }

        if (! $testCaseFound) {
            return $this->exceptionFor($suiteClassName, $suiteClassFile);
        }

        if (! class_exists($suiteClassName, false)) {
            return $this->exceptionFor($suiteClassName, $suiteClassFile);
        }

        // @codeCoverageIgnoreEnd

        if ($class->isSubclassOf(TestCase::class) && ! $class->isAbstract()) {
            return $class;
        }

        if ($class->hasMethod('suite')) {
            try {
                $method = $class->getMethod('suite');
                // @codeCoverageIgnoreStart
            } catch (ReflectionException $e) {
                throw new Exception($e->getMessage(), (int) $e->getCode(), $e);
            }
            // @codeCoverageIgnoreEnd

            if (! $method->isAbstract() && $method->isPublic() && $method->isStatic()) {
                return $class;
            }
        }

        return $this->exceptionFor($suiteClassName, $suiteClassFile);
    }

    public function reload(ReflectionClass $aClass): ReflectionClass
    {
        return $aClass;
    }

    private function classNameFromFileName(string $suiteClassFile): string
    {
        $className = basename($suiteClassFile, '.php');
        $dotPos = strpos($className, '.');

        if ($dotPos !== false) {
            $className = substr($className, 0, $dotPos);
        }

        return $className;
    }

    private function exceptionFor(string $className, string $filename): ReflectionClass
    {
        return new ReflectionClass(IgnorableTestCase::class);
    }
}
