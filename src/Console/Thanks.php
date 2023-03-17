<?php

declare(strict_types=1);

namespace Pest\Console;

use Pest\Bootstrappers\BootView;
use Pest\Support\View;
use Symfony\Component\Console\Helper\SymfonyQuestionHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
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
    public function __construct(
        private readonly InputInterface $input,
        private readonly OutputInterface $output
    ) {
        // ..
    }

    /**
     * Executes the Console Command.
     */
    public function __invoke(): void
    {
        $bootstrapper = new BootView($this->output);
        $bootstrapper->boot();

        $wantsToSupport = false;

        if (getenv('PEST_NO_SUPPORT') !== 'true' && $this->input->isInteractive()) {
            $wantsToSupport = (new SymfonyQuestionHelper())->ask(
                new ArrayInput([]),
                $this->output,
                new ConfirmationQuestion(
                    ' <options=bold>Wanna show Pest some love by starring it on GitHub?</>',
                    false,
                )
            );

            View::render('components.new-line');

            foreach (self::FUNDING_MESSAGES as $message => $link) {
                View::render('components.two-column-detail', [
                    'left' => $message,
                    'right' => $link,
                ]);
            }

            View::render('components.new-line');
        }

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
    }
}
