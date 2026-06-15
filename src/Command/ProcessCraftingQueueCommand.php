<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Crafting\CraftingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:process-crafting-queue',
    description: 'Complete due crafting jobs and grant crafted items.',
)]
class ProcessCraftingQueueCommand extends Command
{
    public function __construct(
        private readonly CraftingService $craftingService,
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
            } catch (\Exception) {
                $io->error(sprintf('Invalid time format: "%s". Please use standard Y-m-d H:i:s.', $timeStr));

                return Command::FAILURE;
            }
        } else {
            $now = new \DateTimeImmutable('now');
        }

        try {
            $result = $this->craftingService->processDueJobs($now);
            $io->success(sprintf(
                'Crafting queue processed at %s. Completed: %d, failed: %d.',
                $now->format('Y-m-d H:i:s'),
                $result['completed'],
                $result['failed']
            ));
        } catch (\Throwable $e) {
            $io->error(sprintf('Error during crafting queue processing: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
