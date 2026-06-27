<?php

declare(strict_types=1);

namespace App\Service\Headquarters;

use App\Entity\Headquarters\Facility;
use App\Entity\Headquarters\Headquarters;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;
use App\Enum\FacilityType;
use App\Enum\FinancialRecordActor;
use App\Enum\FinancialRecordType;
use App\Enum\RoyalTreasuryContributionSource;
use App\Exception\UserFacingException;
use App\Repository\Headquarters\HeadquartersRepository;
use App\Service\Economy\EconomyService;
use App\Service\Economy\FinancialCrisisService;
use App\Service\Economy\RoyalTreasuryService;
use App\Service\TeamChronicle\TeamChronicleService;
use Doctrine\ORM\EntityManagerInterface;

class HeadquartersService
{
    /**
     * Base upgrade cost per facility (gold).
     * Actual cost = base × 1.5^(current_level - 1) × total_level_multiplier.
     *
     * @var array<string, int>
     */
    private const UPGRADE_BASE_COSTS = [
        'training' => 500,
        'medical' => 400,
        'library' => 600,
        'treasury' => 450,
        'barracks' => 350,
        'summoning_chamber' => 800,
        'arena' => 900,
    ];

    /** +2.5% upgrade cost per HQ total level point above the starting sum. */
    private const UPGRADE_TOTAL_LEVEL_FACTOR = 0.025;

    /** Partial refund ratio when a facility downgrade completes (anti-exploit). */
    private const DOWNGRADE_REFUND_RATIO = 0.25;

    public function __construct(
        private readonly HeadquartersRepository $hqRepository,
        private readonly EconomyService $economyService,
        private readonly FinancialCrisisService $financialCrisisService,
        private readonly RoyalTreasuryService $royalTreasuryService,
        private readonly TeamChronicleService $teamChronicleService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Create a Headquarters with all facilities at level 1 for a newly registered team.
     * Must be called during team setup. Idempotent — returns existing HQ if already initialized.
     */
    public function initializeForTeam(Team $team): Headquarters
    {
        $existing = $this->hqRepository->findOneBy(['team' => $team]);
        if (null !== $existing) {
            return $existing;
        }

        $hq = new Headquarters();
        $hq->setTeam($team);

        foreach (FacilityType::cases() as $type) {
            $facility = new Facility();
            $facility->setType($type);
            $facility->setLevel(1);
            $hq->addFacility($facility);
            $this->em->persist($facility);
        }

        $hq->syncTotalLevel();

        $this->em->persist($hq);
        $this->em->flush();

        return $hq;
    }

    /**
     * @throws \DomainException if HQ not initialized
     */
    public function getForTeam(Team $team): Headquarters
    {
        $hq = $this->hqRepository->findOneBy(['team' => $team]);
        if (null === $hq) {
            throw new UserFacingException('error.hq_not_initialized');
        }

        return $hq;
    }

    /**
     * Upgrade a specific facility. Deducts gold from the team and schedules completion.
     *
     * @throws \DomainException on insufficient gold, facility not found, or active upgrade
     */
    public function upgradeFacility(Team $team, FacilityType $type, ?\DateTimeImmutable $now = null): Facility
    {
        $now = $now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $hq = $this->getForTeam($team);

        if (null !== $hq->getUpgradingFacility()) {
            throw new UserFacingException('error.hq_facility_change_in_progress');
        }

        if ($hq->isFacilityDowngradeLockCycle()) {
            throw new UserFacingException('error.hq_downgrade_locked');
        }

        $this->financialCrisisService->assertSpendingAllowed($team, 'hq_upgrade');

        $facility = null;
        foreach ($hq->getFacilities() as $f) {
            if ($f->getType() === $type) {
                $facility = $f;
                break;
            }
        }

        if (null === $facility) {
            throw new UserFacingException('error.hq_facility_not_found', ['%type%' => $type->value]);
        }

        $cost = $this->calculateUpgradeCost($type, $facility->getLevel(), $hq->getComputedTotalLevel());
        $this->economyService->deductGold(
            $team,
            $cost,
            FinancialRecordType::HqUpgradeCost,
            FinancialRecordActor::Active,
            ['facility_type' => $type->value, 'new_level' => $facility->getLevel() + 1, 'total_level' => $hq->getComputedTotalLevel()]
        );
        $this->royalTreasuryService->collectFee(
            $team->getKingdom(),
            $cost,
            RoyalTreasuryContributionSource::HqUpgradeCost,
            ['facility_type' => $type->value],
        );

        $targetLevel = $facility->getLevel() + 1;
        $speed = (float) $team->getKingdom()->getGameSpeed();
        if ($speed <= 0.0) {
            $speed = 1.0;
        }
        $seconds = (int) round(($targetLevel * 24 * 3600) / $speed);
        $completedAt = $now->modify(sprintf('+%d seconds', $seconds));

        $hq->setUpgradingFacility($facility);
        $hq->setUpgradeCompletedAt($completedAt);
        $hq->setFacilityOperation(\App\Enum\FacilityOperation::Upgrade);

        $this->em->flush();

        return $facility;
    }

    /**
     * Cancel an active upgrade if it was started less than 1 hour ago.
     * Refunds the full gold cost to the team.
     *
     * @throws \DomainException
     */
    public function cancelUpgrade(Team $team, \DateTimeImmutable $now): void
    {
        $hq = $this->getForTeam($team);
        $facility = $hq->getUpgradingFacility();

        if (null === $facility) {
            throw new UserFacingException('error.hq_no_active_upgrade');
        }

        if (\App\Enum\FacilityOperation::Upgrade !== $hq->getFacilityOperation()) {
            throw new UserFacingException('error.hq_cannot_cancel_downgrade');
        }

        $completedAt = $hq->getUpgradeCompletedAt();
        if (null === $completedAt) {
            throw new UserFacingException('error.hq_no_active_upgrade');
        }

        $targetLevel = $facility->getLevel() + 1;
        $speed = (float) $team->getKingdom()->getGameSpeed();
        if ($speed <= 0.0) {
            $speed = 1.0;
        }

        $seconds = (int) round(($targetLevel * 24 * 3600) / $speed);
        $startedAt = $completedAt->modify(sprintf('-%d seconds', $seconds));

        $elapsed = $now->getTimestamp() - $startedAt->getTimestamp();
        if ($elapsed > 3600) {
            throw new UserFacingException('error.hq_cancel_time_expired');
        }

        // Calculate refund cost (100% refund of upgrade cost)
        $cost = $this->calculateUpgradeCost($facility->getType(), $facility->getLevel(), $hq->getComputedTotalLevel());

        // Refund gold and record transaction
        $this->economyService->addGold(
            $team,
            $cost,
            FinancialRecordType::HqUpgradeRefund,
            FinancialRecordActor::Active,
            ['facility_type' => $facility->getType()->value, 'cancelled_level' => $targetLevel]
        );

        // Reset upgrade state
        $hq->setUpgradingFacility(null);
        $hq->setUpgradeCompletedAt(null);
        $hq->setFacilityOperation(null);

        $this->em->flush();
    }

    /**
     * Downgrade a facility by one level. No upfront cost; a partial refund is paid on completion.
     *
     * @throws \DomainException
     */
    public function downgradeFacility(Team $team, FacilityType $type, ?\DateTimeImmutable $now = null): Facility
    {
        $now = $now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $hq = $this->getForTeam($team);

        if (null !== $hq->getUpgradingFacility()) {
            throw new UserFacingException('error.hq_facility_change_in_progress');
        }

        if ($hq->isFacilityDowngradeLockCycle()) {
            throw new UserFacingException('error.hq_downgrade_locked');
        }

        $facility = null;
        foreach ($hq->getFacilities() as $f) {
            if ($f->getType() === $type) {
                $facility = $f;
                break;
            }
        }

        if (null === $facility) {
            throw new UserFacingException('error.hq_facility_not_found', ['%type%' => $type->value]);
        }

        if ($facility->getLevel() <= 1) {
            throw new UserFacingException('error.hq_facility_min_level');
        }

        $speed = (float) $team->getKingdom()->getGameSpeed();
        if ($speed <= 0.0) {
            $speed = 1.0;
        }
        $seconds = (int) round(($facility->getLevel() * 12 * 3600) / $speed);
        $completedAt = $now->modify(sprintf('+%d seconds', $seconds));

        $hq->setUpgradingFacility($facility);
        $hq->setUpgradeCompletedAt($completedAt);
        $hq->setFacilityOperation(\App\Enum\FacilityOperation::Downgrade);

        $this->financialCrisisService->recordRecoveryAction($team);
        $this->em->flush();

        return $facility;
    }

    public function calculateDowngradeRefund(FacilityType $type, int $currentLevel, int $totalLevel): int
    {
        $upgradeCost = $this->calculateUpgradeCost($type, $currentLevel - 1, $totalLevel);

        return (int) round($upgradeCost * self::DOWNGRADE_REFUND_RATIO);
    }

    public function calculateUpgradeCost(FacilityType $type, int $currentLevel, int $totalLevel): int
    {
        $base = self::UPGRADE_BASE_COSTS[$type->value];
        $levelCost = $base * (1.5 ** ($currentLevel - 1));

        return (int) round($levelCost * $this->getTotalLevelCostMultiplier($totalLevel));
    }

    /**
     * @return array{total: int, hq: int, facilities: int}
     */
    public function calculateWeeklyMaintenanceBreakdown(Headquarters $hq): array
    {
        return HqMaintenanceCalculator::calculateWeeklyMaintenanceBreakdown($hq);
    }

    public function calculateWeeklyMaintenanceFee(Headquarters $hq): int
    {
        return $this->calculateWeeklyMaintenanceBreakdown($hq)['total'];
    }

    public function processMaintenanceTick(Kingdom $kingdom, ?Team $team = null): void
    {
        if (null !== $team) {
            $hq = $this->hqRepository->findOneBy(['team' => $team]);
            $hqs = null !== $hq ? [$hq] : [];
        } else {
            $hqs = $this->hqRepository->findByKingdom($kingdom);
        }

        foreach ($hqs as $hq) {
            /** @var Headquarters $hq */
            $team = $hq->getTeam();
            $breakdown = $this->calculateWeeklyMaintenanceBreakdown($hq);
            $feeDue = $breakdown['total'];

            if ($feeDue <= 0) {
                continue;
            }

            $deducted = min($team->getGold(), $feeDue);
            $unpaid = $feeDue - $deducted;

            if ($deducted > 0) {
                $this->economyService->deductGold(
                    $team,
                    $deducted,
                    FinancialRecordType::HqMaintenanceFee,
                    FinancialRecordActor::System,
                    [
                        'fee_due' => $feeDue,
                        'hq_fee' => $breakdown['hq'],
                        'facilities_fee' => $breakdown['facilities'],
                        'total_level' => $hq->getComputedTotalLevel(),
                        'unpaid' => $unpaid,
                    ]
                );
                $this->royalTreasuryService->collectFee(
                    $kingdom,
                    $deducted,
                    RoyalTreasuryContributionSource::HqMaintenanceFee,
                    ['team_id' => $team->getId()],
                );
            }

            if ($unpaid > 0) {
                $this->financialCrisisService->addUnpaidDebt($team, $unpaid);

                if (0 === $deducted) {
                    $this->economyService->recordLedgerEntry(
                        $team,
                        FinancialRecordType::HqMaintenanceFee,
                        FinancialRecordActor::System,
                        [
                            'fee_due' => $feeDue,
                            'hq_fee' => $breakdown['hq'],
                            'facilities_fee' => $breakdown['facilities'],
                            'total_level' => $hq->getComputedTotalLevel(),
                            'unpaid' => $unpaid,
                            'fully_unpaid' => true,
                        ]
                    );
                }
            }
        }

        $this->em->flush();
    }

    private function getTotalLevelCostMultiplier(int $totalLevel): float
    {
        $startingTotal = count(FacilityType::cases());
        $excess = max(0, $totalLevel - $startingTotal);

        return 1.0 + ($excess * self::UPGRADE_TOTAL_LEVEL_FACTOR);
    }

    /**
     * Returns the summoning chamber's passive bonuses for a team.
     * Falls back to an empty array if the team has no HQ or no chamber yet.
     *
     * @return array{summon_base_stat_bonus?: float, summon_stat_random_bonus?: float, summon_stat_total_cap?: float}
     */
    public function getSummoningChamberBonuses(Team $team): array
    {
        /** @var Headquarters|null $hq */
        $hq = $this->hqRepository->findOneBy(['team' => $team]);
        if (null === $hq) {
            return [];
        }

        foreach ($hq->getFacilities() as $facility) {
            if (FacilityType::SummoningChamber === $facility->getType()) {
                /* @var array{summon_base_stat_bonus?: float, summon_stat_random_bonus?: float} */
                return $facility->getPassiveBonuses();
            }
        }

        return [];
    }

    /** @return array<string, mixed> */
    public function serializeFacility(Facility $facility, FacilityType $type, Headquarters $hq): array
    {
        $totalLevel = $hq->getComputedTotalLevel();
        $canChange = null === $hq->getUpgradingFacility() && !$hq->isFacilityDowngradeLockCycle();

        return [
            'type' => $facility->getType()->value,
            'level' => $facility->getLevel(),
            'passive_bonuses' => $facility->getPassiveBonuses(),
            'upgrade_cost' => $this->calculateUpgradeCost($type, $facility->getLevel(), $totalLevel),
            'can_upgrade' => $canChange,
            'can_downgrade' => $canChange && $facility->getLevel() > 1,
            'downgrade_refund' => $facility->getLevel() > 1
                ? $this->calculateDowngradeRefund($type, $facility->getLevel(), $totalLevel)
                : 0,
        ];
    }

    /** Request a pending arena adaptation change (stored in `pending_race_optimization`). */
    public function updateRaceOptimization(Team $team, ?string $raceValue): Headquarters
    {
        $hq = $this->getForTeam($team);

        if ($hq->hasPendingRaceOptimizationChange() || $hq->isRaceOptimizationLockCycle()) {
            throw new UserFacingException('error.hq_race_optimization_locked');
        }

        $this->financialCrisisService->assertSpendingAllowed($team, 'hq_arena_adaptation');

        $current = $hq->getRaceOptimization();

        $target = null;
        if (null !== $raceValue && '' !== trim($raceValue)) {
            $race = \App\Enum\Race::tryFrom($raceValue);
            if (null === $race) {
                throw new UserFacingException('error.hq_invalid_race', ['%race%' => $raceValue]);
            }
            $target = $race->value;
        }

        if ($current === $target) {
            return $hq;
        }

        $hq->setPendingRaceOptimization($target);
        $hq->setHasPendingRaceOptimizationChange(true);
        $this->em->flush();

        return $hq;
    }

    public function getRosterLimit(Team $team): int
    {
        /** @var Headquarters|null $hq */
        $hq = $this->hqRepository->findOneBy(['team' => $team]);
        if (null === $hq) {
            return 10;
        }

        $barracksLevel = 1;
        foreach ($hq->getFacilities() as $facility) {
            if (FacilityType::Barracks === $facility->getType()) {
                $barracksLevel = $facility->getLevel();
                break;
            }
        }

        $bonuses = FacilityType::Barracks->getPassiveBonuses($barracksLevel);
        $barracksBonus = $bonuses['roster_capacity'] ?? 0.0;

        return 10 + (int) round($barracksBonus);
    }

    /** Apply pending arena adaptation changes for all HQ in the kingdom (Sunday tick). */
    public function processRaceOptimizationTick(Kingdom $kingdom, ?Team $team = null): void
    {
        if (null !== $team) {
            $hq = $this->hqRepository->findOneBy(['team' => $team]);
            $hqs = null !== $hq ? [$hq] : [];
        } else {
            $hqs = $this->hqRepository->findByKingdom($kingdom);
        }

        foreach ($hqs as $hq) {
            /** @var Headquarters $hq */
            if ($hq->hasPendingRaceOptimizationChange()) {
                $team = $hq->getTeam();
                $targetRace = $hq->getPendingRaceOptimization();

                $hq->setRaceOptimization($targetRace);
                $hq->setPendingRaceOptimization(null);
                $hq->setHasPendingRaceOptimizationChange(false);
                $hq->setRaceOptimizationLockCycle(true);

                $this->teamChronicleService->recordRaceOptimizationChanged($team, $targetRace);
            } elseif ($hq->isRaceOptimizationLockCycle()) {
                $hq->setRaceOptimizationLockCycle(false);
            }
        }

        $this->em->flush();
    }

    public function processFacilityUpgradesTick(Kingdom $kingdom, \DateTimeImmutable $now, ?Team $team = null): void
    {
        if (null !== $team) {
            $hq = $this->hqRepository->findOneBy(['team' => $team]);
            $hqs = null !== $hq ? [$hq] : [];
        } else {
            $hqs = $this->hqRepository->findByKingdom($kingdom);
        }

        foreach ($hqs as $hq) {
            /** @var Headquarters $hq */
            $changing = $hq->getUpgradingFacility();
            $completedAt = $hq->getUpgradeCompletedAt();
            $operation = $hq->getFacilityOperation();

            if (null === $changing || null === $completedAt || $now < $completedAt) {
                continue;
            }

            $team = $hq->getTeam();
            $type = $changing->getType();

            if (\App\Enum\FacilityOperation::Downgrade === $operation) {
                $previousLevel = $changing->getLevel();
                $newLevel = max(1, $previousLevel - 1);
                $changing->setLevel($newLevel);

                $refund = $this->calculateDowngradeRefund($type, $previousLevel, $hq->getComputedTotalLevel());
                if ($refund > 0) {
                    $this->economyService->addGold(
                        $team,
                        $refund,
                        FinancialRecordType::HqDowngradeRefund,
                        FinancialRecordActor::Active,
                        [
                            'facility_type' => $type->value,
                            'previous_level' => $previousLevel,
                            'new_level' => $newLevel,
                        ]
                    );
                }

                $hq->setFacilityDowngradeLockCycle(true);

                $this->teamChronicleService->recordFacilityDowngraded($team, $type->value, $newLevel);
            } else {
                $newLevel = $changing->getLevel() + 1;
                $changing->setLevel($newLevel);

                $this->teamChronicleService->recordFacilityUpgraded($team, $type->value, $newLevel);
            }

            $hq->setUpgradingFacility(null);
            $hq->setUpgradeCompletedAt(null);
            $hq->setFacilityOperation(null);
            $hq->syncTotalLevel();
        }

        $this->em->flush();
    }

    public function processFacilityDowngradeLockTick(Kingdom $kingdom, ?Team $team = null): void
    {
        if (null !== $team) {
            $hq = $this->hqRepository->findOneBy(['team' => $team]);
            $hqs = null !== $hq ? [$hq] : [];
        } else {
            $hqs = $this->hqRepository->findByKingdom($kingdom);
        }

        foreach ($hqs as $hq) {
            /** @var Headquarters $hq */
            if ($hq->isFacilityDowngradeLockCycle()) {
                $hq->setFacilityDowngradeLockCycle(false);
            }
        }

        $this->em->flush();
    }
}
