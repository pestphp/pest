<?php

declare(strict_types=1);

namespace Pest\Plugins\Tia;

/**
 * @internal
 */
final readonly class GitHubRepository
{
    public const string DEFAULT_HOST = 'github.com';

    private function __construct(
        public string $host,
        public string $name,
    ) {}

    public static function fromProjectRoot(string $projectRoot): ?self
    {
        $gitConfig = $projectRoot.DIRECTORY_SEPARATOR.'.git'.DIRECTORY_SEPARATOR.'config';

        if (! is_file($gitConfig)) {
            return null;
        }

        $content = @file_get_contents($gitConfig);

        if ($content === false) {
            return null;
        }

        if (preg_match('/\[remote "origin"\][^\[]*?url\s*=\s*(\S+)/s', $content, $match) !== 1) {
            return null;
        }

        return self::fromRemoteUrl($match[1]);
    }

    public static function fromRemoteUrl(string $url): ?self
    {
        // user@host:owner/repo(.git)
        if (preg_match('#^[\w.-]+@([\w.-]+):([\w.-]+/[\w.-]+?)(?:\.git)?$#', $url, $m) === 1) {
            return new self(strtolower($m[1]), $m[2]);
        }

        // scheme://[user@]host[:port]/owner/repo(.git)  — https, ssh
        if (preg_match('#^(?:https?|ssh)://(?:[^@/]+@)?([\w.-]+)(?::\d+)?/([\w.-]+/[\w.-]+?)(?:\.git)?/?$#i', $url, $m) === 1) {
            return new self(strtolower($m[1]), $m[2]);
        }

        return null;
    }

    public function isDefaultHost(): bool
    {
        return $this->host === self::DEFAULT_HOST;
    }

    public function qualifiedName(): string
    {
        return $this->isDefaultHost() ? $this->name : $this->host.'/'.$this->name;
    }

    /**
     * @return array<int, string>
     */
    public function hostnameArguments(): array
    {
        return $this->isDefaultHost() ? [] : ['--hostname', $this->host];
    }
}
