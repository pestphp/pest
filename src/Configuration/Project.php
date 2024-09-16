<?php

declare(strict_types=1);

namespace Pest\Configuration;

/**
 * @internal
 */
final class Project
{
    /**
     * The assignees link.
     *
     * @internal
     */
    public string $assignees = '';

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
     * Creates a new instance of the project.
     */
    public static function getInstance(): self
    {
        return self::$instance ??= new self;
    }

    /**
     * Sets the test project to GitHub.
     */
    public function github(string $project): self
    {
        $this->issues = "https://github.com/{$project}/issues/%s";
        $this->prs = "https://github.com/{$project}/pull/%s";

        $this->assignees = 'https://github.com/%s';

        return $this;
    }

    /**
     * Sets the test project to GitLab.
     */
    public function gitlab(string $project): self
    {
        $this->issues = "https://gitlab.com/{$project}/issues/%s";
        $this->prs = "https://gitlab.com/{$project}/merge_requests/%s";

        $this->assignees = 'https://gitlab.com/%s';

        return $this;
    }

    /**
     * Sets the test project to Bitbucket.
     */
    public function bitbucket(string $project): self
    {
        $this->issues = "https://bitbucket.org/{$project}/issues/%s";
        $this->prs = "https://bitbucket.org/{$project}/pull-requests/%s";

        $this->assignees = 'https://bitbucket.org/%s';

        return $this;
    }

    /**
     * Sets the test project to Jira.
     */
    public function jira(string $namespace, string $project): self
    {
        $this->issues = "https://{$namespace}.atlassian.net/browse/{$project}-%s";

        $this->assignees = "https://{$namespace}.atlassian.net/secure/ViewProfile.jspa?name=%s";

        return $this;
    }

    /**
     * Sets the test project to custom.
     */
    public function custom(string $issues, string $prs, string $assignees): self
    {
        $this->issues = $issues;
        $this->prs = $prs;

        $this->assignees = $assignees;

        return $this;
    }
}
