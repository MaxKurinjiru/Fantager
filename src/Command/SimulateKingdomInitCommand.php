<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\PacePreset;
use App\Service\Auth\TestUserService;
use App\Service\Kingdom\KingdomInitializationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:kingdom:simulate:init',
    description: 'Create an NPC-only kingdom for multi-season balance simulation.',
)]
class SimulateKingdomInitCommand extends Command
{
    public function __construct(
        private readonly KingdomInitializationService $initializationService,
        private readonly TestUserService $testUserService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $paceValues = implode(', ', array_map(
            static fn (PacePreset $case): string => $case->value,
            PacePreset::cases(),
        ));

        $this
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Kingdom name', 'Balance Sim')
            ->addOption(
                'pace',
                null,
                InputOption::VALUE_REQUIRED,
                'Pace preset. One of: '.$paceValues.'. Default: classic.',
                PacePreset::Classic->value,
            )
            ->addOption('with-test-users', null, InputOption::VALUE_NONE, 'Also create player1–3@example.com (claims three NPC teams)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Print only JSON (kingdom_id, name, teams, heroes)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $asJson = (bool) $input->getOption('json');
        $name = trim((string) $input->getOption('name'));
        $pace = (string) $input->getOption('pace');

        try {
            $result = $this->initializationService->initialize($name, false, null, $pace);
        } catch (\DomainException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $kingdom = $result['kingdom'];
        $kingdomId = (int) $kingdom->getId();
        $payload = [
            'kingdom_id' => $kingdomId,
            'name' => $kingdom->getName(),
            'pace' => $kingdom->getPacePreset()->value,
            'teams' => $result['teams'],
            'heroes' => $result['heroes'],
        ];

        if ((bool) $input->getOption('with-test-users')) {
            $users = $this->testUserService->ensureDefaultTestUsers($kingdom);
            $payload['test_users_created'] = count($users['created']);
            $payload['test_users_skipped'] = count($users['skipped']);
        }

        if ($asJson) {
            $output->writeln((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return Command::SUCCESS;
        }

        $io->success(sprintf(
            'Created NPC kingdom "%s" (id=%d, pace=%s): %d teams, %d heroes.',
            $payload['name'],
            $kingdomId,
            $payload['pace'],
            $payload['teams'],
            $payload['heroes'],
        ));
        $io->writeln(sprintf('kingdom_id=%d', $kingdomId));
        $io->note('Next: app:kingdom:simulate:advance --kingdom-id='.$kingdomId.' then app:kingdom:simulate:process until idle.');

        return Command::SUCCESS;
    }
}
