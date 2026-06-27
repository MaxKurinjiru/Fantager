<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\Kingdom\KingdomRepository;
use App\Service\Calendar\KingdomTickOrchestrator;
use App\Service\Calendar\KingdomTickRunnerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:ticks:run',
    description: 'Check and schedule pending server ticks for all active kingdoms',
)]
class ProcessTicksCommand extends Command
{
    public function __construct(
        private readonly KingdomRepository $kingdomRepository,
        private readonly KingdomTickRunnerService $tickRunnerService,
        private readonly KingdomTickOrchestrator $tickOrchestrator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('time', null, InputOption::VALUE_OPTIONAL, 'Override check execution time (e.g. "2026-06-08 19:30:00")')
             ->addOption('kingdom-id', null, InputOption::VALUE_OPTIONAL, 'Run only for a specific Kingdom ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $timeStr = $input->getOption('time');
        $kingdomIdOpt = $input->getOption('kingdom-id');

        if (null !== $timeStr) {
            try {
                $now = new \DateTimeImmutable((string) $timeStr, new \DateTimeZone('UTC'));
            } catch (\Exception $e) {
                $io->error(sprintf('Invalid time format: "%s". Please use standard Y-m-d H:i:s.', $timeStr));

                return Command::FAILURE;
            }
        } else {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        }

        if (null !== $kingdomIdOpt) {
            $kingdoms = $this->kingdomRepository->findBy(['id' => (int) $kingdomIdOpt]);
            if (empty($kingdoms)) {
                $io->error(sprintf('Kingdom with ID %s not found.', $kingdomIdOpt));

                return Command::FAILURE;
            }
        } else {
            $kingdoms = $this->kingdomRepository->findAll();
        }

        $io->section(sprintf('Processing ticks at %s UTC...', $now->format('Y-m-d H:i:s')));

        foreach ($kingdoms as $kingdom) {
            /** @var \App\Entity\Kingdom\Kingdom $kingdom */
            $kingdomId = $kingdom->getId();
            if (null === $kingdomId) {
                continue;
            }

            $io->text(sprintf('Checking Kingdom: %s (ID: %d)', $kingdom->getName(), $kingdomId));

            $blocked = $this->tickRunnerService->findBlockedTick($kingdom);
            if (null !== $blocked) {
                $io->warning(sprintf(
                    'Kingdom ID %d has a blocked tick (ID: %d, type: %s, status: %s). Skipping scheduling.',
                    $kingdomId,
                    $blocked->getId(),
                    $blocked->getTickType()->value,
                    $blocked->getStatus(),
                ));
                continue;
            }

            $hasPendingBefore = $this->tickRunnerService->hasPendingTicks($kingdom);
            $ticksDispatched = $this->tickRunnerService->schedulePendingTicks($kingdom, $now);

            if ($ticksDispatched > 0 || $hasPendingBefore) {
                if ($ticksDispatched > 0) {
                    $io->success(sprintf('Scheduled %d new ticks for Kingdom %s.', $ticksDispatched, $kingdom->getName()));
                } else {
                    $io->text(sprintf('Found existing pending ticks for Kingdom %s. Resuming processing.', $kingdom->getName()));
                }
                $this->tickOrchestrator->orchestrate($kingdom);
            } else {
                $io->text(sprintf('Kingdom %s is up-to-date.', $kingdom->getName()));
            }
        }

        return Command::SUCCESS;
    }
}
