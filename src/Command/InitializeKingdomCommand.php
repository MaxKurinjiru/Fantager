<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Auth\TestUserService;
use App\Service\Kingdom\KingdomInitializationService;
use App\Service\Kingdom\KingdomService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:kingdom:initialize',
    description: 'Create a new kingdom with season 1, NPC teams, heroes, and league standings from config/kingdom/*.defaults.json',
)]
class InitializeKingdomCommand extends Command
{
    public function __construct(
        private readonly KingdomInitializationService $initializationService,
        private readonly KingdomService $kingdomService,
        private readonly TestUserService $testUserService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Display name of the new kingdom (server)')
            ->addOption(
                'test',
                null,
                InputOption::VALUE_NONE,
                'Test mode: season starts last Monday and creates 3 default test users (player1–3@example.com / password)',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = (string) $input->getArgument('name');
        $testMode = (bool) $input->getOption('test');

        try {
            $result = $this->initializationService->initialize($name, $testMode);
        } catch (\DomainException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $kingdom = $result['kingdom'];
        $capacity = $this->kingdomService->calculateCapacity($kingdom);

        $io->success(sprintf(
            'Kingdom "%s" (id=%d) initialized: %d NPC teams, %d heroes, capacity %d.',
            $kingdom->getName(),
            $kingdom->getId(),
            $result['teams'],
            $result['heroes'],
            $capacity,
        ));

        if ($testMode) {
            try {
                $users = $this->testUserService->createDefaultTestUsers($kingdom);
            } catch (\DomainException $e) {
                $io->error($e->getMessage());

                return Command::FAILURE;
            }

            $io->success(sprintf(
                'Created %d test users: %s',
                count($users),
                implode(', ', array_map(static fn ($user) => $user->getEmail(), $users)),
            ));
        }

        $io->note('Defaults loaded from config/kingdom/*.defaults.json — edit those files to tune bootstrap values.');

        return Command::SUCCESS;
    }
}
