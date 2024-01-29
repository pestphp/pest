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

/*
 * This file is part of PHPUnit.
 *
 * (c) Sebastian Bergmann <sebastian@phpunit.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PHPUnit\TextUI\Command;

use PHPUnit\TextUI\Configuration\CodeCoverageFilterRegistry;
use PHPUnit\TextUI\Configuration\Configuration;
use PHPUnit\TextUI\Configuration\NoCoverageCacheDirectoryException;
use SebastianBergmann\CodeCoverage\StaticAnalysis\CacheWarmer;
use SebastianBergmann\Timer\NoActiveTimerException;
use SebastianBergmann\Timer\Timer;

/**
 * @internal This class is not covered by the backward compatibility promise for PHPUnit
 */
final class WarmCodeCoverageCacheCommand implements Command
{
    private readonly Configuration $configuration;

    private readonly CodeCoverageFilterRegistry $codeCoverageFilterRegistry;

    public function __construct(Configuration $configuration, CodeCoverageFilterRegistry $codeCoverageFilterRegistry)
    {
        $this->configuration = $configuration;
        $this->codeCoverageFilterRegistry = $codeCoverageFilterRegistry;
    }

    /**
     * @throws NoActiveTimerException
     * @throws NoCoverageCacheDirectoryException
     */
    public function execute(): Result
    {
        if (! $this->configuration->hasCoverageCacheDirectory()) {
            return Result::from(
                'Cache for static analysis has not been configured'.PHP_EOL,
                Result::FAILURE
            );
        }

        $this->codeCoverageFilterRegistry->init($this->configuration);

        if (! $this->codeCoverageFilterRegistry->configured()) {
            return Result::from(
                'Filter for code coverage has not been configured'.PHP_EOL,
                Result::FAILURE
            );
        }

        $timer = new Timer;
        $timer->start();

        (new CacheWarmer)->warmCache(
            $this->configuration->coverageCacheDirectory(),
            ! $this->configuration->disableCodeCoverageIgnore(),
            $this->configuration->ignoreDeprecatedCodeUnitsFromCodeCoverage(),
            $this->codeCoverageFilterRegistry->get()
        );

        return Result::from();
    }
}
