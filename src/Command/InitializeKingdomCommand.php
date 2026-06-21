<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Auth\TestUserService;
use App\Service\Calendar\KingdomTickRunnerService;
use App\Service\Kingdom\KingdomInitializationService;
use App\Service\Kingdom\KingdomService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Bootstrap a new kingdom (server) from config/kingdom/*.defaults.json.
 *
 * Typical local dev — kingdom already ~1 month into season 1:
 *
 *   php bin/console app:kingdom:initialize "Main Kingdom" \
 *     --test \
 *     --start-offset-days=-21 \
 *     --catch-up-ticks
 *
 * - --test anchors season start to last Monday (not next Monday).
 * - --start-offset-days=-21 shifts that anchor ~3 weeks further into the past (~4 game weeks total).
 * - --catch-up-ticks runs all missed server ticks synchronously (training, aging, matches, economy).
 *
 * Without --catch-up-ticks you can schedule ticks later via: php bin/console app:ticks:run --kingdom-id=<id>
 */
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
        private readonly KingdomTickRunnerService $tickRunnerService,
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
            )
            ->addOption(
                'start-offset-days',
                null,
                InputOption::VALUE_REQUIRED,
                'Shift season start relative to the Monday anchor (negative = further in the past). '
                .'Overrides config/kingdom/season.defaults.json → start_offset_days. '
                .'Example for ~1 month of history: --test --start-offset-days=-21',
            )
            ->addOption(
                'catch-up-ticks',
                null,
                InputOption::VALUE_NONE,
                'After bootstrap, synchronously process all server ticks from season start through now. '
                .'Use with a past season start (--test and/or negative --start-offset-days).',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = (string) $input->getArgument('name');
        $testMode = (bool) $input->getOption('test');
        $catchUpTicks = (bool) $input->getOption('catch-up-ticks');

        $startOffsetDaysOverride = null;
        $startOffsetDaysRaw = $input->getOption('start-offset-days');
        if (null !== $startOffsetDaysRaw && '' !== $startOffsetDaysRaw) {
            if (!is_numeric($startOffsetDaysRaw)) {
                $io->error(sprintf('Invalid --start-offset-days value "%s"; expected an integer.', $startOffsetDaysRaw));

                return Command::FAILURE;
            }
            $startOffsetDaysOverride = (int) $startOffsetDaysRaw;
        }

        if ($catchUpTicks && null === $startOffsetDaysOverride && !$testMode) {
            $io->warning(
                'Using --catch-up-ticks without --test or negative --start-offset-days: '
                .'season may start in the future, so few or no ticks will run.',
            );
        }

        try {
            $result = $this->initializationService->initialize($name, $testMode, $startOffsetDaysOverride);
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

        if ($catchUpTicks) {
            $blocked = $this->tickRunnerService->findBlockedTick($kingdom);
            if (null !== $blocked) {
                $io->error(sprintf(
                    'Cannot catch up ticks: kingdom has a blocked tick (id=%d, type=%s, status=%s).',
                    $blocked->getId(),
                    $blocked->getTickType()->value,
                    $blocked->getStatus(),
                ));

                return Command::FAILURE;
            }

            $io->section('Catching up server ticks (season start → now)...');
            $until = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $scheduled = $this->tickRunnerService->schedulePendingTicks($kingdom, $until);

            if (0 === $scheduled) {
                $io->note('No pending ticks to process — season start may still be in the future.');
            } else {
                $this->tickRunnerService->processPendingTicksSynchronously($kingdom);

                $blockedAfter = $this->tickRunnerService->findBlockedTick($kingdom);
                if (null !== $blockedAfter) {
                    $io->error(sprintf(
                        'Tick catch-up stopped: tick id=%d (%s) is %s. %s',
                        $blockedAfter->getId(),
                        $blockedAfter->getTickType()->value,
                        $blockedAfter->getStatus(),
                        $blockedAfter->getErrorMessage() ?? '',
                    ));

                    return Command::FAILURE;
                }

                $io->success(sprintf('Processed %d server ticks.', $scheduled));
            }
        }

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
