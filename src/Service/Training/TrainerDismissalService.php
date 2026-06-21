<?php

declare(strict_types=1);

namespace App\Service\Training;

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
use App\Service\TeamChronicle\TeamChronicleService;
use Doctrine\ORM\EntityManagerInterface;

class TrainerDismissalService
{
    public const COMPENSATION_RATIO = 0.3;
    public const BASE_VALUE_PER_STAT_POINT = 3;

    public function __construct(
        private readonly GraveyardService $graveyardService,
        private readonly EconomyService $economyService,
        private readonly FinancialCrisisService $financialCrisisService,
        private readonly TeamChronicleService $teamChronicleService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function estimateTrainerValue(Hero $trainer): int
    {
        $statSum = $trainer->getStr() + $trainer->getDex() + $trainer->getKon() + $trainer->getSpd()
            + $trainer->getIntel() + $trainer->getWil() + $trainer->getCha() + $trainer->getLck();

        return (int) round($statSum * self::BASE_VALUE_PER_STAT_POINT);
    }

    /**
     * @throws \DomainException
     */
    public function dismiss(Team $team, Hero $trainer): int
    {
        if (!$trainer->isTrainer()) {
            throw new UserFacingException('error.trainer_not_entity');
        }

        if ($trainer->getTeam()->getId() !== $team->getId()) {
            throw new UserFacingException('error.trainer_not_on_team');
        }

        if (HeroStatus::Available !== $trainer->getStatus()) {
            throw new UserFacingException('error.trainer_only_active_dismiss');
        }

        $estimatedValue = $this->estimateTrainerValue($trainer);
        $compensation = (int) round($estimatedValue * self::COMPENSATION_RATIO);

        $this->graveyardService->prepareTrainerRemoval($trainer);
        $this->graveyardService->recordMemorial($trainer, $team, MemorialCause::Dismissed);

        if ($compensation > 0) {
            $this->economyService->addGold(
                $team,
                $compensation,
                FinancialRecordType::TrainerDismissalCompensation,
                FinancialRecordActor::Active,
                [
                    'trainer_id' => $trainer->getId(),
                    'trainer_name' => $trainer->getName(),
                    'estimated_value' => $estimatedValue,
                ]
            );
        }

        $this->teamChronicleService->recordTrainerDismissed($team, $trainer, $compensation);

        $this->financialCrisisService->recordRecoveryAction($team);
        $this->graveyardService->removeHero($trainer);
        $this->em->flush();

        return $compensation;
    }
}
