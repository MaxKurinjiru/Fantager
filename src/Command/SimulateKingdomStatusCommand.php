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
    name: 'app:kingdom:simulate:status',
    description: 'Show simulation pipeline state. Exit 0 idle, 2 busy, 1 failed.',
)]
class SimulateKingdomStatusCommand extends Command
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
        } catch (\DomainException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $snapshot = $this->simulationService->getPipelineSnapshot($kingdom);
        $exit = $this->simulationService->pipelineExitCode($snapshot);
        $state = match ($exit) {
            KingdomSimulationService::EXIT_IDLE => 'idle',
            KingdomSimulationService::EXIT_FAILED => 'failed',
            default => 'busy',
        };

        $payload = [
            'state' => $state,
            'kingdom_id' => $snapshot['kingdom_id'],
            'name' => $snapshot['name'],
            'completed_seasons' => $snapshot['completed_seasons'],
            'open_ticks' => $snapshot['open_ticks'],
            'messenger_async_count' => $snapshot['messenger_async_count'],
            'latest_completed_at' => $snapshot['latest_completed_at'],
            'oldest_pending_at' => $snapshot['oldest_pending_at'],
        ];

        if ((bool) $input->getOption('json')) {
            $output->writeln((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $exit;
        }

        $io->definitionList(
            ['State' => $state],
            ['Completed seasons' => (string) $payload['completed_seasons']],
            ['Open ticks' => (string) $payload['open_ticks']],
            ['Messenger' => (string) $payload['messenger_async_count']],
            ['Latest completed' => $payload['latest_completed_at'] ?? 'none'],
            ['Oldest pending' => $payload['oldest_pending_at'] ?? 'none'],
        );

        return $exit;
    }
}
