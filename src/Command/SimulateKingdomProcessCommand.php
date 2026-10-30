<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Kingdom\KingdomSimulationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:kingdom:simulate:process',
    description: 'Consume one bounded Messenger batch (or resume the orchestrator). Exit 0 idle, 2 still busy, 1 failed.',
)]
class SimulateKingdomProcessCommand extends Command
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
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Messenger message limit for this process', '200')
            ->addOption('time-limit', null, InputOption::VALUE_REQUIRED, 'Messenger time limit in seconds', '45')
            ->addOption('memory-limit', null, InputOption::VALUE_REQUIRED, 'Messenger memory limit', '128M')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Print a JSON snapshot after the batch');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $kingdomIdRaw = $input->getOption('kingdom-id');
        if (null === $kingdomIdRaw || '' === (string) $kingdomIdRaw) {
            $io->error('Pass --kingdom-id.');

            return Command::FAILURE;
        }
        if (!is_numeric($input->getOption('limit')) || (int) $input->getOption('limit') < 1) {
            $io->error('--limit must be a positive integer.');

            return Command::FAILURE;
        }
        if (!is_numeric($input->getOption('time-limit')) || (int) $input->getOption('time-limit') < 5) {
            $io->error('--time-limit must be at least 5 seconds.');

            return Command::FAILURE;
        }

        $kingdomId = (int) $kingdomIdRaw;
        $asJson = (bool) $input->getOption('json');

        try {
            $kingdom = $this->simulationService->requireKingdom($kingdomId);
        } catch (\DomainException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $snapshot = $this->simulationService->getPipelineSnapshot($kingdom);
        $exit = $this->simulationService->pipelineExitCode($snapshot);
        if (KingdomSimulationService::EXIT_FAILED === $exit) {
            $this->writeSnapshot($io, $output, $asJson, $snapshot, 'failed');

            return KingdomSimulationService::EXIT_FAILED;
        }
        if (KingdomSimulationService::EXIT_IDLE === $exit) {
            $this->writeSnapshot($io, $output, $asJson, $snapshot, 'idle');

            return KingdomSimulationService::EXIT_IDLE;
        }

        $queue = (int) $snapshot['messenger_async_count'];
        if ($queue > 0) {
            $batch = min((int) $input->getOption('limit'), max(1, $queue));
            $code = $this->consumeMessenger(
                $output,
                $batch,
                (int) $input->getOption('time-limit'),
                (string) $input->getOption('memory-limit'),
            );
            if (Command::SUCCESS !== $code) {
                $io->error('messenger:consume failed. Pipeline is halted.');

                return KingdomSimulationService::EXIT_FAILED;
            }
        } else {
            $this->simulationService->resumeOrchestration($kingdom);
        }

        $kingdom = $this->simulationService->reloadKingdom($kingdomId);
        $snapshot = $this->simulationService->getPipelineSnapshot($kingdom);
        $exit = $this->simulationService->pipelineExitCode($snapshot);
        $this->writeSnapshot($io, $output, $asJson, $snapshot, match ($exit) {
            KingdomSimulationService::EXIT_IDLE => 'idle',
            KingdomSimulationService::EXIT_FAILED => 'failed',
            default => 'busy',
        });

        return $exit;
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function writeSnapshot(
        SymfonyStyle $io,
        OutputInterface $output,
        bool $asJson,
        array $snapshot,
        string $state,
    ): void {
        $payload = [
            'state' => $state,
            'kingdom_id' => $snapshot['kingdom_id'] ?? null,
            'completed_seasons' => $snapshot['completed_seasons'] ?? 0,
            'open_ticks' => $snapshot['open_ticks'] ?? 0,
            'messenger_async_count' => $snapshot['messenger_async_count'] ?? 0,
            'latest_completed_at' => $snapshot['latest_completed_at'] ?? null,
        ];
        if ($asJson) {
            $output->writeln((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return;
        }

        $io->text(sprintf(
            'state=%s seasons=%s open_ticks=%s messenger=%s latest=%s',
            $state,
            (string) $payload['completed_seasons'],
            (string) $payload['open_ticks'],
            (string) $payload['messenger_async_count'],
            (string) ($payload['latest_completed_at'] ?? 'none'),
        ));
    }

    private function consumeMessenger(OutputInterface $output, int $limit, int $timeLimit, string $memoryLimit): int
    {
        $application = $this->getApplication();
        if (null === $application) {
            throw new \LogicException('Console application is not available.');
        }

        $command = $application->find('messenger:consume');
        $consumeInput = new ArrayInput([
            'command' => 'messenger:consume',
            'receivers' => ['async'],
            '--limit' => (string) $limit,
            '--time-limit' => (string) $timeLimit,
            '--memory-limit' => $memoryLimit,
            '--failure-limit' => '1',
            '--no-interaction' => true,
        ]);
        $consumeInput->setInteractive(false);

        return $command->run($consumeInput, $output);
    }
}
