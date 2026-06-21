<?php

declare(strict_types=1);

namespace App\Service\Team;

use App\Entity\Team\Team;
use App\Repository\Hero\HeroRepository;
use App\Service\Config\RaceConfig;
use Doctrine\ORM\EntityManagerInterface;

class TeamChemistryService
{
    public function __construct(
        private readonly HeroRepository $heroRepository,
        private readonly RaceConfig $raceConfig,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function recalculate(Team $team): void
    {
        $heroes = $this->heroRepository->findCombatantsByTeam($team);
        $count = count($heroes);

        if ($count < 2) {
            $team->setChemistry(50);
            $this->em->flush();

            return;
        }

        $totalRelationship = 0;
        $pairsCount = 0;
        $hostilePairsCount = 0;

        for ($i = 0; $i < $count; ++$i) {
            for ($j = $i + 1; $j < $count; ++$j) {
                $heroA = $heroes[$i];
                $heroB = $heroes[$j];

                $base = $this->raceConfig->getRelationship($heroA->getRace(), $heroB->getRace());

                $effective = $base;
                if ($base < 50) {
                    $chaA = $heroA->getCha();
                    $chaB = $heroB->getCha();
                    $chaOffset = max(0, $chaA - 10) + max(0, $chaB - 10);
                    $effective = min(50, $base + $chaOffset);
                }

                if ($effective <= 20) {
                    ++$hostilePairsCount;
                }

                $totalRelationship += $effective;
                ++$pairsCount;
            }
        }

        $avgRelationship = $totalRelationship / $pairsCount;
        $penalty = $hostilePairsCount * 15;

        $finalChemistry = (int) round($avgRelationship - $penalty);
        $finalChemistry = max(0, min(100, $finalChemistry));

        $team->setChemistry($finalChemistry);
        $this->em->flush();
    }
}
