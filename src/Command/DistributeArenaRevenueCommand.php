<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Economy\ArenaRevenueService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:economy:distribute-arena-revenue',
    description: 'Distribute weekly Arena ticket revenue to all active non-NPC teams',
)]
class DistributeArenaRevenueCommand extends Command
{
    public function __construct(
        private readonly ArenaRevenueService $arenaRevenueService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('time', null, InputOption::VALUE_OPTIONAL, 'Override check execution time (e.g. "2026-06-07 23:59:00")');
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
            $reports = $this->arenaRevenueService->distributeWeeklyRevenue();

            if (empty($reports)) {
                $io->warning('No eligible teams found for arena revenue distribution.');
            } else {
                $io->section('Arena Revenue Distribution Summary');

                $headers = ['Team ID', 'Team Name', 'Capacity', 'Attendance', 'Gold Earned'];
                $rows = [];
                foreach ($reports as $report) {
                    $rows[] = [
                        $report['team_id'],
                        $report['team_name'],
                        $report['capacity'],
                        $report['attendance'],
                        $report['gold_earned'],
                    ];
                }
                $io->table($headers, $rows);

                $io->success(sprintf('Weekly Arena revenue distributed successfully at %s.', $now->format('Y-m-d H:i:s')));
            }
        } catch (\Throwable $e) {
            $io->error(sprintf('Error during arena revenue distribution: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
