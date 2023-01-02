<?php

declare(strict_types=1);

namespace Pest\Repositories;

/**
 * @internal
 */
final class RetryRepository
{
    private const TEMPORARY_FOLDER = __DIR__
        .DIRECTORY_SEPARATOR
        .'..'
        .DIRECTORY_SEPARATOR
        .'..'
        .DIRECTORY_SEPARATOR
        .'.temp';

    /**
     * Creates a new Temp Repository instance.
     */
    public function __construct(private readonly string $filename)
    {
        // ..
    }

    /**
     * Adds a new element.
     */
    public function add(string $element): void
    {
        $this->save([...$this->all(), ...[$element]]);
    }

    /**
     * Clears the existing file, if any, and re-creates it.
     */
    public function boot(): void
    {
        @unlink(self::TEMPORARY_FOLDER.'/'.$this->filename.'.json'); // @phpstan-ignore-line

        $this->save([]);
    }

    /**
     * Checks if there is any element.
     */
    public function isEmpty(): bool
    {
        return $this->all() === [];
    }

    /**
     * Checks if the given element exists.
     */
    public function exists(string $element): bool
    {
        return in_array($element, $this->all(), true);
    }

    /**
     * Gets all elements.
     *
     * @return array<int, string>
     */
    private function all(): array
    {
        $path = self::TEMPORARY_FOLDER.'/'.$this->filename.'.json';

        $contents = file_exists($path) ? file_get_contents($path) : '{}';

        assert(is_string($contents));

        $all = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        return is_array($all) ? $all : [];
    }

    /**
     * Save the given elements.
     *
     * @param  array<int, string>  $elements
     */
    private function save(array $elements): void
    {
        $contents = json_encode($elements, JSON_THROW_ON_ERROR);

        file_put_contents(self::TEMPORARY_FOLDER.'/'.$this->filename.'.json', $contents);
    }
}
