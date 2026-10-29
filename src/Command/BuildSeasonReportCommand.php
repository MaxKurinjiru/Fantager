<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\League\LeagueSeason;
use App\Repository\Kingdom\KingdomRepository;
use App\Repository\League\LeagueSeasonRepository;
use App\Service\League\SeasonReportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:season:report:build',
    description: 'Build or rebuild frozen season reports for completed seasons.',
)]
class BuildSeasonReportCommand extends Command
{
    public function __construct(
        private readonly KingdomRepository $kingdomRepository,
        private readonly LeagueSeasonRepository $seasonRepository,
        private readonly SeasonReportService $seasonReportService,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('kingdom-id', null, InputOption::VALUE_REQUIRED, 'Kingdom ID')
            ->addOption('season-id', null, InputOption::VALUE_REQUIRED, 'League season ID')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Overwrite an existing report');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $force = (bool) $input->getOption('force');
        $seasonId = $input->getOption('season-id');
        $kingdomId = $input->getOption('kingdom-id');

        if (null !== $seasonId) {
            $season = $this->seasonRepository->find((int) $seasonId);
            if (!$season instanceof LeagueSeason) {
                $io->error(sprintf('Season %s not found.', (string) $seasonId));

                return Command::FAILURE;
            }
            $this->seasonReportService->recordForSeason($season, null, $force);
            $this->em->flush();
            $io->success(sprintf('Season report written for season %d.', $season->getSeasonNumber()));

            return Command::SUCCESS;
        }

        if (null === $kingdomId) {
            $io->error('Pass --kingdom-id or --season-id.');

            return Command::FAILURE;
        }

        $kingdom = $this->kingdomRepository->find((int) $kingdomId);
        if (null === $kingdom) {
            $io->error(sprintf('Kingdom %s not found.', (string) $kingdomId));

            return Command::FAILURE;
        }

        $written = $this->seasonReportService->backfillKingdom($kingdom, $force);
        $this->em->flush();
        $io->success(sprintf('Wrote %d season report(s) for kingdom %s.', count($written), $kingdom->getName()));

        return Command::SUCCESS;
    }
}
