<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\Kingdom\KingdomRepository;
use App\Repository\League\SeasonReportRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:season:report:export',
    description: 'Export frozen season reports as JSONL and print health alerts.',
)]
class ExportSeasonReportCommand extends Command
{
    public function __construct(
        private readonly KingdomRepository $kingdomRepository,
        private readonly SeasonReportRepository $reportRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('kingdom-id', null, InputOption::VALUE_REQUIRED, 'Kingdom ID')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Write JSONL to this file instead of stdout')
            ->addOption('alerts-only', null, InputOption::VALUE_NONE, 'Do not write JSONL; print alerts only');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $alertsOnly = (bool) $input->getOption('alerts-only');
        $outputPath = $input->getOption('output');
        $jsonlToStdout = !$alertsOnly && (!is_string($outputPath) || '' === $outputPath);
        $messages = $jsonlToStdout && $output instanceof ConsoleOutputInterface
            ? new SymfonyStyle($input, $output->getErrorOutput())
            : $io;
        $kingdomId = $input->getOption('kingdom-id');
        if (null === $kingdomId) {
            $messages->error('Pass --kingdom-id.');

            return Command::FAILURE;
        }

        $kingdom = $this->kingdomRepository->find((int) $kingdomId);
        if (null === $kingdom) {
            $messages->error(sprintf('Kingdom %s not found.', (string) $kingdomId));

            return Command::FAILURE;
        }

        $reports = $this->reportRepository->findByKingdomOrdered($kingdom);
        if ([] === $reports) {
            $messages->warning('No season reports for this kingdom. Run app:season:report:build first.');

            return Command::SUCCESS;
        }

        $lines = [];
        $alertRows = [];

        foreach ($reports as $report) {
            $payload = $report->getPayload();
            $lines[] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $health = is_array($payload['health'] ?? null) ? $payload['health'] : [];
            foreach ($health as $row) {
                if (!is_array($row) || 'ok' === ($row['status'] ?? '')) {
                    continue;
                }
                $alertRows[] = [
                    $report->getSeasonNumber(),
                    (string) ($row['metric'] ?? ''),
                    (string) ($row['status'] ?? ''),
                    $row['value'] ?? '',
                    $row['band_low'] ?? '',
                    $row['band_high'] ?? '',
                ];
            }
        }

        if (!$alertsOnly) {
            $jsonl = implode("\n", $lines)."\n";
            if (is_string($outputPath) && '' !== $outputPath) {
                if (false === file_put_contents($outputPath, $jsonl)) {
                    $messages->error(sprintf('Could not write %s.', $outputPath));

                    return Command::FAILURE;
                }
                $messages->success(sprintf('Wrote %d report(s) to %s.', count($lines), $outputPath));
            } else {
                $output->write($jsonl);
            }
        }

        if ([] === $alertRows) {
            $messages->success('No health-band alerts.');
        } else {
            $messages->warning(sprintf('%d health-band alert(s).', count($alertRows)));
            $messages->table(
                ['Season', 'Metric', 'Status', 'Value', 'Min', 'Max'],
                $alertRows,
            );
        }

        return Command::SUCCESS;
    }
}
