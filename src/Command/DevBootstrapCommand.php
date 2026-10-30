<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:dev:bootstrap',
    description: 'Local helper: initialize a test kingdom (~1 month in), create default users, catch up ticks.',
)]
class DevBootstrapCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::OPTIONAL, 'Kingdom display name', 'Main Kingdom')
            ->addOption('start-offset-days', null, InputOption::VALUE_REQUIRED, 'Season start offset (negative = past). Default -21.', '-21')
            ->addOption('pace', null, InputOption::VALUE_REQUIRED, 'Pace preset.', 'classic')
            ->addOption('skip-catch-up', null, InputOption::VALUE_NONE, 'Do not run ticks synchronously after bootstrap');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $application = $this->getApplication();
        if (null === $application) {
            throw new \RuntimeException('Console application is not set.');
        }

        $initialize = $application->find('app:kingdom:initialize');
        $args = [
            'name' => (string) $input->getArgument('name'),
            '--test' => true,
            '--start-offset-days' => (string) $input->getOption('start-offset-days'),
            '--pace' => (string) $input->getOption('pace'),
        ];
        if (!$input->getOption('skip-catch-up')) {
            $args['--catch-up-ticks'] = true;
        }

        $nested = new ArrayInput($args);
        $nested->setInteractive(false);

        return $initialize->run($nested, $output);
    }
}
