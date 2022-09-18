<?php

declare(strict_types=1);

namespace Pest\Console;

use Pest\Support\View;
use Symfony\Component\Console\Helper\SymfonyQuestionHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * @internal
 */
final class Thanks
{
    /**
     * The support options.
     *
     * @var array<string, string>
     */
    private const FUNDING_MESSAGES = [
        'Star the project on GitHub' => 'https://github.com/pestphp/pest',
        'Tweet about the project' => 'https://twitter.com/pestphp',
        'Sponsor the project' => 'https://github.com/sponsors/nunomaduro',
    ];

    /**
     * Creates a new Console Command instance.
     */
    public function __construct(private readonly OutputInterface $output)
    {
        // ..
    }

    /**
     * Executes the Console Command.
     */
    public function __invoke(): void
    {
        $wantsToSupport = (new SymfonyQuestionHelper())->ask(
            new ArrayInput([]),
            $this->output,
            new ConfirmationQuestion(
                ' <options=bold>Would you like to show your support by starring the project on GitHub?</>',
                true,
            )
        );

        if ($wantsToSupport === true) {
            if (PHP_OS_FAMILY == 'Darwin') {
                exec('open https://github.com/pestphp/pest');
            }
            if (PHP_OS_FAMILY == 'Windows') {
                exec('start https://github.com/pestphp/pest');
            }
            if (PHP_OS_FAMILY == 'Linux') {
                exec('xdg-open https://github.com/pestphp/pest');
            }
        }

        View::render('components.new-line');

        foreach (self::FUNDING_MESSAGES as $message => $link) {
            View::render('components.two-column-detail', [
                'left' => $message,
                'right' => $link,
            ]);
        }

        View::render('components.new-line');
    }
}
