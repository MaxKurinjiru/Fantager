<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Hero\Hero;
use App\Repository\Hero\HeroRepository;
use App\Service\Hero\HeroProgressionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:hero-levels:refresh',
    description: 'Recompute stored Hero.level from lifetime XP using the current curve and kingdom cap (no chronicle).',
)]
class RefreshHeroLevelsCommand extends Command
{
    public function __construct(
        private readonly HeroRepository $heroRepository,
        private readonly HeroProgressionService $heroProgressionService,
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
            if ($this->heroProgressionService->recalcStoredLevel($hero, $hero->getTeam())) {
                ++$count;
            }
        }

        $this->em->flush();

        $io->success(sprintf('Recalculated stored level for %d heroes.', $count));

        return Command::SUCCESS;
    }
}
