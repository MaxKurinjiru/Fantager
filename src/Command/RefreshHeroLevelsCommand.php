<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\Hero\HeroRepository;
use App\Repository\Kingdom\KingdomRepository;
use App\Service\Hero\HeroProgressionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:hero-levels:refresh',
    description: 'Recompute stored Hero.level from lifetime XP using the current curve and kingdom cap (no chronicle).',
)]
class RefreshHeroLevelsCommand extends Command
{
    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly HeroRepository $heroRepository,
        private readonly KingdomRepository $kingdomRepository,
        private readonly HeroProgressionService $heroProgressionService,
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
        $seen = 0;
        foreach ($this->heroRepository->iterateWithTeam($kingdomId) as $hero) {
            if ($this->heroProgressionService->recalcStoredLevel($hero, $hero->getTeam())) {
                ++$count;
            }
            ++$seen;
            if (0 === $seen % self::BATCH_SIZE) {
                $this->em->flush();
            }
        }

        $this->em->flush();

        $io->success(sprintf('Recalculated stored level for %d of %d heroes.', $count, $seen));

        return Command::SUCCESS;
    }
}
