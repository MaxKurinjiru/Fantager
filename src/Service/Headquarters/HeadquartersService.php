<?php

declare(strict_types=1);

namespace App\Service\Headquarters;

use App\Entity\Headquarters\Facility;
use App\Entity\Headquarters\Headquarters;
use App\Entity\Team\Team;
use App\Enum\FacilityType;
use App\Repository\Headquarters\HeadquartersRepository;
use App\Service\Economy\EconomyService;
use Doctrine\ORM\EntityManagerInterface;

class HeadquartersService
{
    /**
     * Base upgrade cost per facility (gold). Actual cost = base * 1.5^(current_level - 1).
     *
     * @var array<string, int>
     */
    private const UPGRADE_BASE_COSTS = [
        'training' => 500,
        'medical' => 400,
        'library' => 600,
        'forge' => 700,
        'treasury' => 450,
        'barracks' => 350,
        'summoning_chamber' => 800,
        'arena' => 900,
    ];

    public function __construct(
        private readonly HeadquartersRepository $hqRepository,
        private readonly EconomyService $economyService,
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
            throw new \DomainException('Headquarters not initialized for this team.');
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
            throw new \DomainException('Another facility upgrade is already in progress.');
        }

        $facility = null;
        foreach ($hq->getFacilities() as $f) {
            if ($f->getType() === $type) {
                $facility = $f;
                break;
            }
        }

        if (null === $facility) {
            throw new \DomainException(sprintf('Facility "%s" not found in HQ.', $type->value));
        }

        $cost = $this->calculateUpgradeCost($type, $facility->getLevel());
        $this->economyService->deductGold(
            $team,
            $cost,
            \App\Enum\FinancialRecordType::HqUpgradeCost,
            \App\Enum\FinancialRecordActor::Active,
            ['facility_type' => $type->value, 'new_level' => $facility->getLevel() + 1]
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

        $this->em->flush();

        return $facility;
    }

    public function calculateUpgradeCost(FacilityType $type, int $currentLevel): int
    {
        $base = self::UPGRADE_BASE_COSTS[$type->value] ?? 500;

        return (int) round($base * (1.5 ** ($currentLevel - 1)));
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
    public function serializeFacility(Facility $facility, FacilityType $type): array
    {
        return [
            'type' => $facility->getType()->value,
            'level' => $facility->getLevel(),
            'passive_bonuses' => $facility->getPassiveBonuses(),
            'upgrade_cost' => $this->calculateUpgradeCost($type, $facility->getLevel()),
        ];
    }

    public function updateRaceOptimization(Team $team, ?string $raceValue): Headquarters
    {
        $hq = $this->getForTeam($team);

        if ($hq->hasPendingRaceOptimizationChange() || $hq->isRaceOptimizationLockCycle()) {
            throw new \DomainException('Race optimization is currently locked.');
        }

        $current = $hq->getRaceOptimization();

        $target = null;
        if (null !== $raceValue && '' !== trim($raceValue)) {
            $race = \App\Enum\Race::tryFrom($raceValue);
            if (null === $race) {
                throw new \DomainException(sprintf('Invalid race: %s.', $raceValue));
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

    public function processRaceOptimizationTick(\App\Entity\Kingdom\Kingdom $kingdom): void
    {
        $hqs = $this->hqRepository->findByKingdom($kingdom);

        foreach ($hqs as $hq) {
            /** @var Headquarters $hq */
            if ($hq->hasPendingRaceOptimizationChange()) {
                $hq->setRaceOptimization($hq->getPendingRaceOptimization());
                $hq->setPendingRaceOptimization(null);
                $hq->setHasPendingRaceOptimizationChange(false);
                $hq->setRaceOptimizationLockCycle(true);
            } elseif ($hq->isRaceOptimizationLockCycle()) {
                $hq->setRaceOptimizationLockCycle(false);
            }
        }

        $this->em->flush();
    }

    public function processFacilityUpgradesTick(\App\Entity\Kingdom\Kingdom $kingdom, \DateTimeImmutable $now): void
    {
        $hqs = $this->hqRepository->findByKingdom($kingdom);

        foreach ($hqs as $hq) {
            /** @var Headquarters $hq */
            $upgrading = $hq->getUpgradingFacility();
            $completedAt = $hq->getUpgradeCompletedAt();

            if (null !== $upgrading && null !== $completedAt && $now >= $completedAt) {
                $newLevel = $upgrading->getLevel() + 1;
                $upgrading->setLevel($newLevel);

                $hq->setUpgradingFacility(null);
                $hq->setUpgradeCompletedAt(null);

                // Recalculate HQ total level
                $total = 0;
                foreach ($hq->getFacilities() as $f) {
                    $total += $f->getLevel();
                }
                $hq->setTotalLevel($total);
            }
        }

        $this->em->flush();
    }
}
