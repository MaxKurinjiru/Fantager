<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\Hero\HeroRepository;
use App\Repository\Kingdom\KingdomRepository;
use App\Service\Hero\HeroRatingCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:hero-ratings:refresh',
    description: 'Recompute and persist cached base_ovr and complex_rating for all heroes.',
)]
class RefreshHeroRatingsCommand extends Command
{
    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly HeroRepository $heroRepository,
        private readonly KingdomRepository $kingdomRepository,
        private readonly HeroRatingCalculator $heroRatingCalculator,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('kingdom-id', null, InputOption::VALUE_REQUIRED, 'Limit to heroes in this kingdom');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $kingdomIdOpt = $input->getOption('kingdom-id');
        $kingdomId = null;
        if (null !== $kingdomIdOpt && '' !== $kingdomIdOpt) {
            $kingdomId = (int) $kingdomIdOpt;
            if (null === $this->kingdomRepository->find($kingdomId)) {
                $io->error(sprintf('Kingdom %s not found.', (string) $kingdomIdOpt));

                return Command::FAILURE;
            }
        }

        $count = 0;
        foreach ($this->heroRepository->iterateWithTeam($kingdomId) as $hero) {
            $rating = $this->heroRatingCalculator->calculate($hero);
            $hero->setBaseOvr($rating->getBaseOvr());
            $hero->setComplexRating($rating->getComplexRating());
            ++$count;
            if (0 === $count % self::BATCH_SIZE) {
                $this->em->flush();
            }
        }

        $this->em->flush();

        $io->success(sprintf('Refreshed ratings for %d heroes.', $count));

        return Command::SUCCESS;
    }
}
