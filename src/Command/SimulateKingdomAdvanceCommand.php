<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Kingdom\KingdomSimulationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:kingdom:simulate:advance',
    description: 'Schedule one simulation step (week or season) and dispatch ticks. Does not consume Messenger.',
)]
class SimulateKingdomAdvanceCommand extends Command
{
    public function __construct(
        private readonly KingdomSimulationService $simulationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('kingdom-id', null, InputOption::VALUE_REQUIRED, 'Kingdom ID')
            ->addOption('step', null, InputOption::VALUE_REQUIRED, 'week or season (11 weeks)', 'week')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print the horizon; do not schedule')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Print JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $kingdomIdRaw = $input->getOption('kingdom-id');
        if (null === $kingdomIdRaw || '' === (string) $kingdomIdRaw) {
            $io->error('Pass --kingdom-id.');

            return Command::FAILURE;
        }

        try {
            $kingdom = $this->simulationService->requireKingdom((int) $kingdomIdRaw);
            $step = $this->simulationService->stepInterval((string) $input->getOption('step'));
            $until = $this->simulationService->nextHorizon($kingdom, $step);
        } catch (\DomainException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $snapshot = $this->simulationService->getPipelineSnapshot($kingdom);
        $exit = $this->simulationService->pipelineExitCode($snapshot);
        $dryRun = (bool) $input->getOption('dry-run');
        $asJson = (bool) $input->getOption('json');

        if (KingdomSimulationService::EXIT_FAILED === $exit) {
            $this->writeResult($io, $output, $asJson, [
                'ok' => false,
                'reason' => 'blocked',
                'until' => $until->format('Y-m-d H:i:s'),
                'scheduled' => 0,
            ] + $snapshot);

            return KingdomSimulationService::EXIT_FAILED;
        }

        if (KingdomSimulationService::EXIT_BUSY === $exit) {
            $this->writeResult($io, $output, $asJson, [
                'ok' => false,
                'reason' => 'busy',
                'until' => $until->format('Y-m-d H:i:s'),
                'scheduled' => 0,
            ] + $snapshot);
            if (!$asJson) {
                $io->warning('Pipeline is not idle. Run app:kingdom:simulate:process until it returns 0, then advance again.');
            }

            return KingdomSimulationService::EXIT_BUSY;
        }

        if ($dryRun) {
            $this->writeResult($io, $output, $asJson, [
                'ok' => true,
                'dry_run' => true,
                'until' => $until->format('Y-m-d H:i:s'),
                'scheduled' => 0,
                'completed_seasons' => $snapshot['completed_seasons'],
            ]);

            return Command::SUCCESS;
        }

        try {
            $scheduled = $this->simulationService->scheduleThrough($kingdom, $until);
        } catch (\DomainException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $this->writeResult($io, $output, $asJson, [
            'ok' => true,
            'until' => $until->format('Y-m-d H:i:s'),
            'scheduled' => $scheduled,
            'completed_seasons' => $snapshot['completed_seasons'],
        ]);
        if (!$asJson) {
            $io->note('Process this step with: php bin/console app:kingdom:simulate:process --kingdom-id='.(int) $kingdom->getId());
        }

        return Command::SUCCESS;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeResult(SymfonyStyle $io, OutputInterface $output, bool $asJson, array $payload): void
    {
        if ($asJson) {
            $output->writeln((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return;
        }

        if (isset($payload['reason'])) {
            $io->error(sprintf('Advance skipped (%s).', (string) $payload['reason']));
        } elseif (!empty($payload['dry_run'])) {
            $io->text(sprintf('Dry-run horizon %s UTC.', (string) $payload['until']));
        } else {
            $io->success(sprintf(
                'Scheduled %d tick(s) through %s UTC.',
                (int) $payload['scheduled'],
                (string) $payload['until'],
            ));
        }
    }
}
