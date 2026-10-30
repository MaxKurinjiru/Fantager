<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\Kingdom\KingdomRepository;
use App\Service\Calendar\KingdomTickRunnerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:ticks:retry',
    description: 'Reset failed kingdom ticks to pending after the cause is fixed. Then run app:ticks:run.',
)]
class RetryFailedTicksCommand extends Command
{
    public function __construct(
        private readonly KingdomRepository $kingdomRepository,
        private readonly KingdomTickRunnerService $tickRunnerService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('kingdom-id', null, InputOption::VALUE_REQUIRED, 'Kingdom ID')
            ->addOption('tick-id', null, InputOption::VALUE_REQUIRED, 'Retry a single failed tick log ID');
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

        try {
            $logs = $this->tickRunnerService->retryFailedTicks($kingdom, $tickId);
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
        $io->success(sprintf('Reset %d failed tick(s) to pending. Run app:ticks:run --kingdom-id=%d next.', count($logs), $kingdom->getId()));

        return Command::SUCCESS;
    }
}
