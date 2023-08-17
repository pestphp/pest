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

namespace PHPUnit\Runner\Filter;

use function end;
use Exception;
use function implode;
use Pest\Contracts\HasPrintableTestCaseName;
use PHPUnit\Framework\SelfDescribing;
use PHPUnit\Framework\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestSuite;
use function preg_match;
use RecursiveFilterIterator;
use RecursiveIterator;
use function sprintf;
use function str_replace;

/**
 * @internal This class is not covered by the backward compatibility promise for PHPUnit
 */
final class NameFilterIterator extends RecursiveFilterIterator
{
    private ?string $filter = null;

    private ?int $filterMin = null;

    private ?int $filterMax = null;

    /**
     * @throws Exception
     */
    public function __construct(RecursiveIterator $iterator, string $filter)
    {
        parent::__construct($iterator);

        $this->setFilter($filter);
    }

    public function accept(): bool
    {
        $test = $this->getInnerIterator()->current();

        if ($test instanceof TestSuite) {
            return true;
        }

        $tmp = $this->describe($test);

        if ($tmp[0] !== '') {
            $name = implode('::', $tmp);
        } else {
            $name = $tmp[1];
        }

        $accepted = @preg_match($this->filter, $name, $matches);

        if ($accepted && isset($this->filterMax)) {
            $set = end($matches);
            $accepted = $set >= $this->filterMin && $set <= $this->filterMax;
        }

        return (bool) $accepted;
    }

    /**
     * @throws Exception
     */
    private function setFilter(string $filter): void
    {
        if (@preg_match($filter, '') === false) {
            // Handles:
            //  * testAssertEqualsSucceeds#4
            //  * testAssertEqualsSucceeds#4-8
            if (preg_match('/^(.*?)#(\d+)(?:-(\d+))?$/', $filter, $matches)) {
                if (isset($matches[3]) && $matches[2] < $matches[3]) {
                    $filter = sprintf(
                        '%s.*with dataset #(\d+)$',
                        $matches[1]
                    );

                    $this->filterMin = (int) $matches[2];
                    $this->filterMax = (int) $matches[3];
                } else {
                    $filter = sprintf(
                        '%s.*with dataset #%s$',
                        $matches[1],
                        $matches[2]
                    );
                }
            } // Handles:
            //  * testDetermineJsonError@JSON_ERROR_NONE
            //  * testDetermineJsonError@JSON.*
            elseif (preg_match('/^(.*?)@(.+)$/', $filter, $matches)) {
                $filter = sprintf(
                    '%s.*with dataset "%s"$',
                    $matches[1],
                    $matches[2]
                );
            }

            // Escape delimiters in regular expression. Do NOT use preg_quote,
            // to keep magic characters.
            $filter = sprintf(
                '/%s/i',
                str_replace(
                    '/',
                    '\\/',
                    $filter
                )
            );
        }

        $this->filter = $filter;
    }

    /**
     * @psalm-return array{0: string, 1: string}
     */
    private function describe(Test $test): array
    {
        if ($test instanceof HasPrintableTestCaseName) {
            return [
                $test::getPrintableTestCaseName(),
                $test->getPrintableTestCaseMethodName(),
            ];
        }

        if ($test instanceof TestCase) {
            return [$test::class, $test->nameWithDataSet()];
        }

        if ($test instanceof SelfDescribing) {
            return ['', $test->toString()];
        }

        return ['', $test::class];
    }
}
