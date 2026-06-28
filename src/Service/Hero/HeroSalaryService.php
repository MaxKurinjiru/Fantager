<?php

declare(strict_types=1);

namespace App\Service\Hero;

use App\Config\HeroRatingConfig;
use App\Entity\Hero\Hero;

/** Computes hero/trainer weekly salary from intrinsic complex rating. */
class HeroSalaryService
{
    public function __construct(
        private readonly HeroRatingCalculator $heroRatingCalculator,
        private readonly HeroRatingConfig $heroRatingConfig,
    ) {
    }

    public function calculateWeeklySalary(Hero $hero): int
    {
        $rating = $this->heroRatingCalculator->calculate($hero);
        $factor = $hero->isTrainer()
            ? $this->heroRatingConfig->getTrainerSalaryFactor()
            : $this->heroRatingConfig->getHeroSalaryFactor();

        return (int) round(
            $rating->getComplexRating()
            * $this->heroRatingConfig->getGoldPerComplexPoint()
            * $factor
        );
    }
}
