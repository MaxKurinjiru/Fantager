<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Training\TrainingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:training:tick',
    description: 'Process pending training jobs and apply stat increases',
)]
class ProcessTrainingTickCommand extends Command
{
    public function __construct(
        private readonly TrainingService $trainingService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('time', null, InputOption::VALUE_OPTIONAL, 'Override check execution time (e.g. "2026-06-05 10:00:00")');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $timeStr = $input->getOption('time');

        if (null !== $timeStr) {
            try {
                $now = new \DateTimeImmutable((string) $timeStr);
            } catch (\Exception $e) {
                $io->error(sprintf('Invalid time format: "%s". Please use standard Y-m-d H:i:s.', $timeStr));

                return Command::FAILURE;
            }
        } else {
            $now = new \DateTimeImmutable('now');
        }

        try {
            $this->trainingService->processTrainingTick($now);
            $io->success(sprintf('Training tick processed successfully at %s.', $now->format('Y-m-d H:i:s')));
        } catch (\Throwable $e) {
            $io->error(sprintf('Error during training tick processing: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
