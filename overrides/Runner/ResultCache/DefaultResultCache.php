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

namespace PHPUnit\Runner\ResultCache;

use function array_keys;
use function assert;
use const DIRECTORY_SEPARATOR;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function Pest\version;
use PHPUnit\Framework\TestStatus\TestStatus;
use PHPUnit\Runner\DirectoryCannotBeCreatedException;
use PHPUnit\Runner\Exception;
use PHPUnit\Util\Filesystem;

/**
 * @internal This class is not covered by the backward compatibility promise for PHPUnit
 */
final class DefaultResultCache implements ResultCache
{
    /**
     * @var string
     */
    private const DEFAULT_RESULT_CACHE_FILENAME = '.phpunit.result.cache';

    private readonly string $cacheFilename;

    /**
     * @psalm-var array<string, TestStatus>
     */
    private array $defects = [];

    /**
     * @psalm-var array<string, TestStatus>
     */
    private array $currentDefects = [];

    /**
     * @psalm-var array<string, float>
     */
    private array $times = [];

    public function __construct(string $filepath = null)
    {
        if ($filepath !== null && is_dir($filepath)) {
            $filepath .= DIRECTORY_SEPARATOR.self::DEFAULT_RESULT_CACHE_FILENAME;
        }

        $this->cacheFilename = $filepath ?? $_ENV['PHPUNIT_RESULT_CACHE'] ?? self::DEFAULT_RESULT_CACHE_FILENAME;
    }

    public function setStatus(string $id, TestStatus $status): void
    {
        if ($status->isFailure() || $status->isError()) {
            $this->currentDefects[$id] = $status;
            $this->defects[$id] = $status;
        }
    }

    public function status(string $id): TestStatus
    {
        return $this->defects[$id] ?? TestStatus::unknown();
    }

    public function setTime(string $id, float $time): void
    {
        if (! isset($this->currentDefects[$id])) {
            unset($this->defects[$id]);
        }

        $this->times[$id] = $time;
    }

    public function time(string $id): float
    {
        return $this->times[$id] ?? 0.0;
    }

    public function load(): void
    {
        if (! is_file($this->cacheFilename)) {
            return;
        }

        $data = json_decode(
            file_get_contents($this->cacheFilename),
            true
        );

        if ($data === null) {
            return;
        }

        if (! isset($data['version'])) {
            return;
        }

        if ($data['version'] !== $this->cacheVersion()) {
            return;
        }

        assert(isset($data['defects']) && is_array($data['defects']));
        assert(isset($data['times']) && is_array($data['times']));

        foreach (array_keys($data['defects']) as $test) {
            $data['defects'][$test] = TestStatus::from($data['defects'][$test]);
        }

        $this->defects = $data['defects'];
        $this->times = $data['times'];
    }

    /**
     * @throws Exception
     */
    public function persist(): void
    {
        if (! Filesystem::createDirectory(dirname($this->cacheFilename))) {
            throw new DirectoryCannotBeCreatedException($this->cacheFilename);
        }

        $data = [
            'version' => $this->cacheVersion(),
            'defects' => [],
            'times' => $this->times,
        ];

        foreach ($this->defects as $test => $status) {
            $data['defects'][$test] = $status->asInt();
        }

        file_put_contents(
            $this->cacheFilename,
            json_encode($data),
            LOCK_EX
        );
    }

    /**
     * Returns the cache version.
     */
    private function cacheVersion(): string
    {
        return 'pest_'.version();
    }
}
