<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\Kingdom\KingdomRepository;
use App\Service\Calendar\TickClock;
use App\Service\Economy\ArenaRevenueService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:economy:distribute-arena-revenue',
    description: 'Pay arena ticket revenue for league fixtures scheduled at the given time (home team only)',
)]
class DistributeArenaRevenueCommand extends Command
{
    public function __construct(
        private readonly ArenaRevenueService $arenaRevenueService,
        private readonly KingdomRepository $kingdomRepository,
        private readonly TickClock $tickClock,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('time', null, InputOption::VALUE_OPTIONAL, 'Fixture scheduled time to process (e.g. "2026-06-10 18:00:00")')
            ->addOption('kingdom-id', null, InputOption::VALUE_OPTIONAL, 'Limit to a single kingdom ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $timeStr = $input->getOption('time');

        try {
            $scheduledAt = $timeStr
                ? new \DateTimeImmutable((string) $timeStr)
                : new \DateTimeImmutable('now');
        } catch (\Exception) {
            $io->error(sprintf('Invalid time format: "%s". Please use standard Y-m-d H:i:s.', $timeStr));

            return Command::FAILURE;
        }

        $kingdomId = $input->getOption('kingdom-id');
        $kingdoms = null !== $kingdomId
            ? array_filter([$this->kingdomRepository->find((int) $kingdomId)])
            : $this->kingdomRepository->findAll();

        $allReports = [];
        $this->tickClock->setCustomTime($scheduledAt);
        try {
            foreach ($kingdoms as $kingdom) {
                $reports = $this->arenaRevenueService->processLeagueMatchTick($kingdom, $scheduledAt);
                $allReports = array_merge($allReports, $reports);
            }
        } finally {
            $this->tickClock->setCustomTime(null);
        }

        if ([] === $allReports) {
            $io->warning(sprintf('No scheduled fixtures found at %s.', $scheduledAt->format('Y-m-d H:i:s')));
        } else {
            $io->table(
                ['Home Team', 'Away Team', 'Capacity', 'Attendance', 'Gold (home)'],
                array_map(static fn (array $r): array => [
                    $r['home_team_id'],
                    $r['away_team_id'],
                    $r['capacity'],
                    $r['attendance'],
                    $r['gold_earned'],
                ], $allReports)
            );
            $io->success(sprintf('Arena revenue processed for %d fixture(s).', count($allReports)));
        }

        return Command::SUCCESS;
    }
}
