<?php

declare(strict_types=1);

namespace App\Service\Training;

use App\Entity\Team\Team;
use App\Entity\Training\Trainer;
use App\Enum\FinancialRecordActor;
use App\Enum\FinancialRecordType;
use App\Enum\StaffDepartureCause;
use App\Enum\TrainerStatus;
use App\Service\Economy\EconomyService;
use App\Service\Economy\FinancialCrisisService;
use App\Service\Graveyard\GraveyardService;
use Doctrine\ORM\EntityManagerInterface;

class TrainerDismissalService
{
    public const COMPENSATION_RATIO = 0.3;
    public const BASE_VALUE_PER_STAT_POINT = 3;

    public function __construct(
        private readonly GraveyardService $graveyardService,
        private readonly EconomyService $economyService,
        private readonly FinancialCrisisService $financialCrisisService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function estimateTrainerValue(Trainer $trainer): int
    {
        $statSum = $trainer->getStr() + $trainer->getDex() + $trainer->getKon() + $trainer->getSpd()
            + $trainer->getIntel() + $trainer->getWil() + $trainer->getCha() + $trainer->getLck();

        return (int) round($statSum * self::BASE_VALUE_PER_STAT_POINT);
    }

    /**
     * @throws \DomainException
     */
    public function dismiss(Team $team, Trainer $trainer): int
    {
        if ($trainer->getTeam()->getId() !== $team->getId()) {
            throw new \DomainException('Trainer does not belong to your team.');
        }

        if (TrainerStatus::Active !== $trainer->getStatus()) {
            throw new \DomainException('Only active trainers can be dismissed.');
        }

        $estimatedValue = $this->estimateTrainerValue($trainer);
        $compensation = (int) round($estimatedValue * self::COMPENSATION_RATIO);

        $this->graveyardService->prepareTrainerRemoval($trainer);
        $this->graveyardService->recordTrainer($trainer, $team, StaffDepartureCause::Dismissed);

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

        $this->financialCrisisService->recordRecoveryAction($team);
        $this->graveyardService->removeTrainer($trainer);
        $this->em->flush();

        return $compensation;
    }
}
