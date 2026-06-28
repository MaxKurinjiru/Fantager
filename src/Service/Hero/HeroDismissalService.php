<?php

declare(strict_types=1);

namespace App\Service\Hero;

use App\Config\HeroRatingConfig;
use App\Entity\Hero\Hero;
use App\Entity\Team\Team;
use App\Enum\FinancialRecordActor;
use App\Enum\FinancialRecordType;
use App\Enum\HeroStatus;
use App\Enum\MemorialCause;
use App\Exception\UserFacingException;
use App\Service\Economy\EconomyService;
use App\Service\Economy\FinancialCrisisService;
use App\Service\Graveyard\GraveyardService;
use App\Service\Team\TeamChemistryService;
use App\Service\Team\TeamRosterService;
use App\Service\TeamChronicle\TeamChronicleService;
use Doctrine\ORM\EntityManagerInterface;

class HeroDismissalService
{
    public function __construct(
        private readonly TeamRosterService $teamRosterService,
        private readonly GraveyardService $graveyardService,
        private readonly EconomyService $economyService,
        private readonly FinancialCrisisService $financialCrisisService,
        private readonly TeamChronicleService $teamChronicleService,
        private readonly TeamChemistryService $teamChemistryService,
        private readonly HeroRatingCalculator $heroRatingCalculator,
        private readonly HeroRatingConfig $heroRatingConfig,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function estimateHeroValue(Hero $hero): int
    {
        return $this->heroRatingCalculator->estimateGoldValue($hero);
    }

    public function countCombatReadyHeroes(Team $team): int
    {
        return $this->teamRosterService->countCombatReadyHeroes($team);
    }

    /**
     * @throws \DomainException
     */
    public function dismiss(Team $team, Hero $hero): int
    {
        if ($hero->getTeam()->getId() !== $team->getId()) {
            throw new UserFacingException('error.hero_not_on_team');
        }

        if (HeroStatus::Available !== $hero->getStatus()) {
            throw new UserFacingException('error.hero_only_available_dismiss');
        }

        if (null !== $hero->getTrainer()) {
            throw new UserFacingException('error.hero_assigned_trainer_dismiss');
        }

        $this->teamRosterService->assertCanRemoveCombatReadyHero($team, $hero);

        $estimatedValue = $this->estimateHeroValue($hero);
        $compensation = (int) round(
            $estimatedValue * $this->heroRatingConfig->getDismissCompensationRatio()
        );

        $this->graveyardService->prepareHeroRemoval($hero);
        $this->graveyardService->recordMemorial($hero, $team, MemorialCause::Dismissed);

        if ($compensation > 0) {
            $this->economyService->addGold(
                $team,
                $compensation,
                FinancialRecordType::HeroDismissalCompensation,
                FinancialRecordActor::Active,
                [
                    'hero_id' => $hero->getId(),
                    'hero_name' => $hero->getName(),
                    'estimated_value' => $estimatedValue,
                ]
            );
        }

        $this->teamChronicleService->recordHeroDismissed($team, $hero, $compensation);

        $this->financialCrisisService->recordRecoveryAction($team);
        $this->graveyardService->removeHero($hero);
        $this->em->flush();

        $this->teamChemistryService->recalculate($team);

        return $compensation;
    }
}
