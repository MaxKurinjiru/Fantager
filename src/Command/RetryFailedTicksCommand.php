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
    name: 'app:ticks:retry',
    description: 'Retry failed or interrupted ticks, or resume a combat cohort after stopping workers.',
)]
class RetryFailedTicksCommand extends Command
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
        $this
            ->addOption('kingdom-id', null, InputOption::VALUE_REQUIRED, 'Kingdom ID')
            ->addOption('tick-id', null, InputOption::VALUE_REQUIRED, 'Retry a single tick log ID')
            ->addOption('interrupted', null, InputOption::VALUE_NONE, 'Retry a processing tick only after all tick workers are stopped')
            ->addOption('combat', null, InputOption::VALUE_NONE, 'Resume a combat cohort only after all workers are stopped');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $kingdomId = $input->getOption('kingdom-id');
        if (null === $kingdomId || '' === $kingdomId) {
            $io->error('Pass --kingdom-id.');

            return Command::FAILURE;
        }

        $kingdom = $this->kingdomRepository->find((int) $kingdomId);
        if (null === $kingdom) {
            $io->error(sprintf('Kingdom %s not found.', (string) $kingdomId));

            return Command::FAILURE;
        }

        $tickIdRaw = $input->getOption('tick-id');
        $tickId = null !== $tickIdRaw && '' !== $tickIdRaw ? (int) $tickIdRaw : null;
        $interrupted = (bool) $input->getOption('interrupted');
        $combat = (bool) $input->getOption('combat');

        try {
            if ($combat || $interrupted) {
                if (null === $tickId || $tickId <= 0 || ($interrupted && $combat)) {
                    $io->error('Pass --tick-id with either --interrupted or --combat, after stopping all workers.');

                    return Command::FAILURE;
                }
                if ($combat) {
                    $this->tickOrchestrator->resumeCombat($kingdom, $tickId);
                    $io->success('Combat continuation queued. Restart Messenger workers to resume the cohort.');

                    return Command::SUCCESS;
                }
                $logs = [$this->tickRunnerService->retryInterruptedTick($kingdom, $tickId)];
            } else {
                $logs = $this->tickRunnerService->retryFailedTicks($kingdom, $tickId);
            }
        } catch (\DomainException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if ([] === $logs) {
            $io->success('No failed ticks to retry.');

            return Command::SUCCESS;
        }

        $io->table(
            ['ID', 'Type', 'Scheduled (UTC)'],
            array_map(static fn ($log): array => [
                (string) $log->getId(),
                $log->getTickType()->value,
                $log->getScheduledAt()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            ], $logs),
        );
        $io->success(sprintf('Reset %d tick(s) to pending. Run app:ticks:run --kingdom-id=%d next.', count($logs), $kingdom->getId()));

        return Command::SUCCESS;
    }
}
