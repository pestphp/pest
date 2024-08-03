<?php

declare(strict_types=1);

namespace Pest\Configuration;

/**
 * @internal
 */
final class Context
{
    /**
     * The issues link.
     *
     * @internal
     */
    public string $issues = '';

    /**
     * The PRs link.
     *
     * @internal
     */
    public string $prs = '';

    /**
     * The singleton instance.
     */
    private static ?self $instance = null;

    /**
     * Creates a new instance of the context.
     */
    public static function getInstance(): self
    {
        return self::$instance ??= new self;
    }

    /**
     * Sets the test context to GitHub.
     */
    public function github(string $project): self
    {
        $this->issues = "https://github.com/{$project}/issues/%s";
        $this->prs = "https://github.com/{$project}/pull/%s";

        return $this;
    }

    /**
     * Sets the test context to GitLab.
     */
    public function gitlab(string $project): self
    {
        $this->issues = "https://gitlab.com/{$project}/issues/%s";
        $this->prs = "https://gitlab.com/{$project}/merge_requests/%s";

        return $this;
    }

    /**
     * Sets the test context to Bitbucket.
     */
    public function bitbucket(string $project): self
    {
        $this->issues = 'https://bitbucket.org/{$project}/issues/%s';
        $this->prs = "https://bitbucket.org/{$project}/pull-requests/%s";

        return $this;
    }

    /**
     * Sets the test context to Jira.
     */
    public function jira(string $namespace, string $project): self
    {
        $this->issues = "https://{$namespace}.atlassian.net/browse/{$project}-%s";

        return $this;
    }

    /**
     * Sets the test context to custom.
     */
    public function using(string $issues, string $prs): self
    {
        $this->issues = $issues;
        $this->prs = $prs;

        return $this;
    }
}
