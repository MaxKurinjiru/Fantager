<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\Kingdom\KingdomRepository;
use App\Service\Calendar\TickClock;
use App\Service\League\LeagueMatchResolutionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:league:resolve-pending-fixtures',
    description: 'Resolve scheduled league fixtures whose kickoff is in the past (stub simulator + standings update)',
)]
class ResolvePendingLeagueFixturesCommand extends Command
{
    public function __construct(
        private readonly LeagueMatchResolutionService $matchResolutionService,
        private readonly KingdomRepository $kingdomRepository,
        private readonly TickClock $tickClock,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('kingdom-id', null, InputOption::VALUE_REQUIRED, 'Kingdom ID to process')
            ->addOption('until', null, InputOption::VALUE_OPTIONAL, 'Resolve fixtures scheduled at or before this time (default: now UTC)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $kingdomId = $input->getOption('kingdom-id');

        if (null === $kingdomId || '' === $kingdomId) {
            $io->error('Option --kingdom-id is required.');

            return Command::FAILURE;
        }

        $kingdom = $this->kingdomRepository->find((int) $kingdomId);
        if (null === $kingdom) {
            $io->error(sprintf('Kingdom with id=%s not found.', $kingdomId));

            return Command::FAILURE;
        }

        $untilRaw = $input->getOption('until');
        try {
            $until = $untilRaw
                ? new \DateTimeImmutable((string) $untilRaw, new \DateTimeZone('UTC'))
                : new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        } catch (\Exception) {
            $io->error(sprintf('Invalid --until value "%s". Use Y-m-d H:i:s in UTC.', $untilRaw));

            return Command::FAILURE;
        }

        $this->tickClock->setCustomTime($until);
        try {
            $results = $this->matchResolutionService->resolvePendingFixtures($kingdom, $until);
        } finally {
            $this->tickClock->setCustomTime(null);
        }

        if ([] === $results) {
            $io->warning(sprintf('No pending fixtures found for kingdom %d at or before %s.', $kingdom->getId(), $until->format('Y-m-d H:i:s')));

            return Command::SUCCESS;
        }

        $io->table(
            ['Fixture', 'Home', 'Away', 'Score', 'Forfeit'],
            array_map(static fn (array $r): array => [
                $r['fixture_id'],
                $r['home_team_id'],
                $r['away_team_id'],
                sprintf('%d-%d', $r['home_score'], $r['away_score']),
                $r['is_forfeit'] ? 'yes' : 'no',
            ], $results),
        );

        $io->success(sprintf('Resolved %d fixture(s) for kingdom "%s".', count($results), $kingdom->getName()));

        return Command::SUCCESS;
    }
}
