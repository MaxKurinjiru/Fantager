<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Hero\Hero;
use App\Repository\Hero\HeroRepository;
use App\Service\Hero\HeroRatingCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:hero-ratings:refresh',
    description: 'Recompute and persist cached base_ovr and complex_rating for all heroes.',
)]
class RefreshHeroRatingsCommand extends Command
{
    public function __construct(
        private readonly HeroRepository $heroRepository,
        private readonly HeroRatingCalculator $heroRatingCalculator,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var list<Hero> $heroes */
        $heroes = $this->heroRepository->findAll();
        $count = 0;

        foreach ($heroes as $hero) {
            $rating = $this->heroRatingCalculator->calculate($hero);
            $hero->setBaseOvr($rating->getBaseOvr());
            $hero->setComplexRating($rating->getComplexRating());
            ++$count;
        }

        $this->em->flush();

        $io->success(sprintf('Refreshed ratings for %d heroes.', $count));

        return Command::SUCCESS;
    }
}
