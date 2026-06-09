<?php

declare(strict_types=1);

namespace App\Service\Training;

use App\Entity\Hero\Hero;
use App\Entity\Team\Team;
use App\Entity\Training\Trainer;
use App\Entity\Training\TrainingQueue;
use App\Enum\HeroStatus;
use App\Enum\TrainingStatus;
use App\Enum\TrainingType;
use App\Repository\Headquarters\HeadquartersRepository;
use App\Repository\Training\TrainerRepository;
use App\Repository\Training\TrainingQueueRepository;
use App\Service\Config\RaceConfig;
use App\Service\Economy\EconomyService;
use Doctrine\ORM\EntityManagerInterface;

class TrainingService
{
    /**
     * Base gold cost per training type.
     * Actual cost = base * hero_level * (1 + current_stat / 10).
     */
    private const BASE_COSTS = [
        TrainingType::Attribute->value => 100,
        TrainingType::Magic->value => 150,
        TrainingType::Form->value => 80,
    ];

    /** Trainable primary attribute names. */
    private const PRIMARY_ATTRIBUTES = ['str', 'dex', 'kon', 'spd', 'int', 'wil', 'cha', 'lck'];

    private const PARTIAL_REFUND_RATIO = 0.5;

    public function __construct(
        private readonly TrainingQueueRepository $queueRepository,
        private readonly TrainerRepository $trainerRepository,
        private readonly HeadquartersRepository $hqRepository,
        private readonly RaceConfig $raceConfig,
        private readonly EconomyService $economyService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function getNextTrainingTime(\DateTimeImmutable $now): \DateTimeImmutable
    {
        $fridayThisWeek = $now->modify('this week Friday 10:00:00');
        if ($now < $fridayThisWeek) {
            return $fridayThisWeek;
        }

        return $now->modify('next week Friday 10:00:00');
    }

    /**
     * Returns available training options with calculated costs for a hero.
     *
     * @return list<array{type: string, attribute: string|null, gold_cost: int, execute_at_hours: int}>
     */
    public function getOptions(Hero $hero): array
    {
        $options = [];

        $tzString = $hero->getTeam()->getKingdom()->getTimezone();
        $tz = new \DateTimeZone($tzString);
        $nowLocal = new \DateTimeImmutable('now', $tz);
        $nextTickLocal = $this->getNextTrainingTime($nowLocal);
        $diffSeconds = $nextTickLocal->getTimestamp() - $nowLocal->getTimestamp();
        $executeAtHours = (int) ceil($diffSeconds / 3600);

        foreach (self::PRIMARY_ATTRIBUTES as $attr) {
            $currentValue = $this->getHeroStatByName($hero, $attr);
            $cost = $this->computeCost($hero, TrainingType::Attribute, $attr, $currentValue);

            $options[] = [
                'type' => TrainingType::Attribute->value,
                'attribute' => $attr,
                'gold_cost' => $cost,
                'execute_at_hours' => $executeAtHours,
            ];
        }

        $options[] = [
            'type' => TrainingType::Magic->value,
            'attribute' => null,
            'gold_cost' => $this->computeCost($hero, TrainingType::Magic, null, $hero->getMagicCapacity()),
            'execute_at_hours' => $executeAtHours,
        ];

        $options[] = [
            'type' => TrainingType::Form->value,
            'attribute' => null,
            'gold_cost' => $this->computeCost($hero, TrainingType::Form, null, $hero->getForm()),
            'execute_at_hours' => $executeAtHours,
        ];

        return $options;
    }

    /**
     * Queue a training job for a hero.
     *
     * @throws \DomainException          on validation failure or insufficient gold
     * @throws \InvalidArgumentException on invalid attribute
     */
    public function queue(
        Hero $hero,
        TrainingType $type,
        ?string $attribute,
        ?int $trainerId,
        Team $team,
    ): TrainingQueue {
        $this->validateForQueue($hero, $type, $attribute);

        $currentValue = match ($type) {
            TrainingType::Attribute => $this->getHeroStatByName($hero, $attribute ?? ''),
            TrainingType::Magic => $hero->getMagicCapacity(),
            TrainingType::Form => $hero->getForm(),
        };

        $trainer = null;
        if (null !== $trainerId) {
            /** @var Trainer|null $trainer */
            $trainer = $this->trainerRepository->findOneBy(['id' => $trainerId, 'team' => $team]);
        }

        // Validate Cap limits before queueing
        $heroRawStat = match ($type) {
            TrainingType::Attribute => $this->getHeroRawStatByName($hero, $attribute ?? ''),
            TrainingType::Magic => $hero->getMagicCapacity(),
            TrainingType::Form => $hero->getForm(),
        };

        $cap = match ($type) {
            TrainingType::Attribute => null !== $trainer ? $this->getTrainerRawStatByName($trainer, $attribute ?? '') : 200,
            TrainingType::Magic => 5,
            TrainingType::Form => 100,
        };

        if ($heroRawStat >= $cap) {
            if (TrainingType::Attribute === $type && null !== $trainer) {
                throw new \DomainException('Hero stat is already equal to or higher than trainer stat.');
            }
            throw new \DomainException('Hero has already reached the maximum value for this training.');
        }

        $cost = $this->computeCost($hero, $type, $attribute, $currentValue);
        $this->economyService->deductGold(
            $team,
            $cost,
            \App\Enum\FinancialRecordType::TrainingCost,
            \App\Enum\FinancialRecordActor::Active,
            ['hero_id' => $hero->getId(), 'training_type' => $type->value, 'target_attribute' => $attribute]
        );

        $tzString = $team->getKingdom()->getTimezone();
        $tz = new \DateTimeZone($tzString);
        $nowLocal = new \DateTimeImmutable('now', $tz);
        $executeAtLocal = $this->getNextTrainingTime($nowLocal);
        $executeAtUtc = $executeAtLocal->setTimezone(new \DateTimeZone('UTC'));

        $job = new TrainingQueue();
        $job->setHero($hero);
        $job->setTrainingType($type);
        $job->setTargetAttribute($attribute);
        $job->setTrainer($trainer);
        $job->setGoldCost($cost);
        $job->setStatus(TrainingStatus::Pending);
        $job->setExecuteAt($executeAtUtc);

        $hero->setStatus(HeroStatus::Training);

        $this->em->persist($job);
        $this->em->flush();

        return $job;
    }

    /**
     * Cancel a pending training job. Refunds 50% of gold cost.
     *
     * @throws \DomainException if job cannot be cancelled
     */
    public function cancel(TrainingQueue $job, Team $team): void
    {
        if ($job->getHero()->getTeam()->getId() !== $team->getId()) {
            throw new \DomainException('Training job does not belong to your team.');
        }

        if (TrainingStatus::Pending !== $job->getStatus()) {
            throw new \DomainException('Only pending training jobs can be cancelled.');
        }

        $refund = (int) floor($job->getGoldCost() * self::PARTIAL_REFUND_RATIO);
        $this->economyService->addGold(
            $team,
            $refund,
            \App\Enum\FinancialRecordType::TrainingCost,
            \App\Enum\FinancialRecordActor::Active,
            ['hero_id' => $job->getHero()->getId(), 'cancelled_job_id' => $job->getId()]
        );

        $job->setStatus(TrainingStatus::Cancelled);
        $job->setCompletedAt(new \DateTimeImmutable());

        // Restore hero status if no other pending jobs
        $pendingCount = $this->queueRepository->count([
            'hero' => $job->getHero(),
            'status' => TrainingStatus::Pending,
        ]);

        if ($pendingCount <= 1) {
            $job->getHero()->setStatus(HeroStatus::Available);
        }

        $this->em->flush();
    }

    /**
     * @return list<TrainingQueue>
     */
    public function getQueueByTeam(Team $team): array
    {
        return $this->queueRepository->createQueryBuilder('q')
            ->join('q.hero', 'h')
            ->where('h.team = :team')
            ->andWhere('q.status IN (:statuses)')
            ->setParameter('team', $team)
            ->setParameter('statuses', [TrainingStatus::Pending, TrainingStatus::InProgress])
            ->orderBy('q.executeAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return array<string, mixed> */
    public function serialize(TrainingQueue $job): array
    {
        return [
            'id' => $job->getId(),
            'hero_id' => $job->getHero()->getId(),
            'hero_name' => $job->getHero()->getName(),
            'type' => $job->getTrainingType()->value,
            'attribute' => $job->getTargetAttribute(),
            'gold_cost' => $job->getGoldCost(),
            'status' => $job->getStatus()->value,
            'execute_at' => $job->getExecuteAt()->format(\DateTimeInterface::ATOM),
            'completed_at' => $job->getCompletedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function computeCost(Hero $hero, TrainingType $type, ?string $attribute, int $currentValue): int
    {
        $base = self::BASE_COSTS[$type->value] ?? 100;
        $raw = $base * $hero->getLevel() * (1 + $currentValue / 10);

        // Round to nearest 25
        return (int) (round($raw / 25) * 25);
    }

    private function getHeroStatByName(Hero $hero, string $attr): int
    {
        return match ($attr) {
            'str' => $hero->getStr(),
            'dex' => $hero->getDex(),
            'kon' => $hero->getKon(),
            'spd' => $hero->getSpd(),
            'int' => $hero->getIntel(),
            'wil' => $hero->getWil(),
            'cha' => $hero->getCha(),
            'lck' => $hero->getLck(),
            default => throw new \InvalidArgumentException(sprintf('Unknown attribute "%s".', $attr)),
        };
    }

    private function getTrainerStatByName(Trainer $trainer, string $attr): int
    {
        return match ($attr) {
            'str' => $trainer->getStr(),
            'dex' => $trainer->getDex(),
            'kon' => $trainer->getKon(),
            'spd' => $trainer->getSpd(),
            'int' => $trainer->getIntel(),
            'wil' => $trainer->getWil(),
            'cha' => $trainer->getCha(),
            'lck' => $trainer->getLck(),
            default => throw new \InvalidArgumentException(sprintf('Unknown attribute "%s".', $attr)),
        };
    }

    private function getHeroRawStatByName(Hero $hero, string $attr): int
    {
        return match ($attr) {
            'str' => $hero->getStrRaw(),
            'dex' => $hero->getDexRaw(),
            'kon' => $hero->getKonRaw(),
            'spd' => $hero->getSpdRaw(),
            'int' => $hero->getIntelRaw(),
            'wil' => $hero->getWilRaw(),
            'cha' => $hero->getChaRaw(),
            'lck' => $hero->getLckRaw(),
            default => throw new \InvalidArgumentException(sprintf('Unknown attribute "%s".', $attr)),
        };
    }

    private function getTrainerRawStatByName(Trainer $trainer, string $attr): int
    {
        return match ($attr) {
            'str' => $trainer->getStrRaw(),
            'dex' => $trainer->getDexRaw(),
            'kon' => $trainer->getKonRaw(),
            'spd' => $trainer->getSpdRaw(),
            'int' => $trainer->getIntelRaw(),
            'wil' => $trainer->getWilRaw(),
            'cha' => $trainer->getChaRaw(),
            'lck' => $trainer->getLckRaw(),
            default => throw new \InvalidArgumentException(sprintf('Unknown attribute "%s".', $attr)),
        };
    }

    private function setHeroRawStatByName(Hero $hero, string $attr, int $value): void
    {
        switch ($attr) {
            case 'str': $hero->setStrRaw($value);
                break;
            case 'dex': $hero->setDexRaw($value);
                break;
            case 'kon': $hero->setKonRaw($value);
                break;
            case 'spd': $hero->setSpdRaw($value);
                break;
            case 'int': $hero->setIntelRaw($value);
                break;
            case 'wil': $hero->setWilRaw($value);
                break;
            case 'cha': $hero->setChaRaw($value);
                break;
            case 'lck': $hero->setLckRaw($value);
                break;
            default: throw new \InvalidArgumentException(sprintf('Unknown attribute "%s".', $attr));
        }
    }

    private function validateForQueue(Hero $hero, TrainingType $type, ?string $attribute): void
    {
        if (HeroStatus::Dead === $hero->getStatus()) {
            throw new \DomainException('Dead heroes cannot be trained.');
        }

        if (HeroStatus::InMatch === $hero->getStatus()) {
            throw new \DomainException('Heroes in a match cannot be trained.');
        }

        if (TrainingType::Attribute === $type) {
            if (null === $attribute || !in_array($attribute, self::PRIMARY_ATTRIBUTES, true)) {
                throw new \InvalidArgumentException(sprintf('Invalid attribute. Valid values: %s.', implode(', ', self::PRIMARY_ATTRIBUTES)));
            }
        }
    }

    /**
     * Process all pending training jobs scheduled up to $now.
     */
    public function processTrainingTick(\DateTimeImmutable $now, ?\App\Entity\Kingdom\Kingdom $kingdom = null): void
    {
        $jobs = $this->queueRepository->findPendingDue($now, $kingdom);

        /** @var TrainingQueue $job */
        foreach ($jobs as $job) {
            $job->setStatus(TrainingStatus::InProgress);
            $this->em->flush();

            try {
                $hero = $job->getHero();
                $type = $job->getTrainingType();
                $attribute = $job->getTargetAttribute();
                $trainer = $job->getTrainer();

                if (TrainingType::Attribute === $type && null !== $attribute) {
                    $heroStatExt = $this->getHeroStatByName($hero, $attribute);
                    $heroStatRaw = $this->getHeroRawStatByName($hero, $attribute);

                    if (null !== $trainer) {
                        $trainerStatExt = $this->getTrainerStatByName($trainer, $attribute);
                        $trainerStatRaw = $this->getTrainerRawStatByName($trainer, $attribute);
                        $cap = $trainerStatRaw;
                    } else {
                        $trainerStatExt = 0;
                        $trainerStatRaw = 200;
                        $cap = 200;
                    }

                    if ($heroStatRaw < $cap) {
                        if (null !== $trainer) {
                            $baseGain = 1.0;
                            $trainerFactor = max(0.0, ($trainerStatExt - 10) * 0.05);
                            $diffFactor = max(0.0, ($trainerStatExt - $heroStatExt) * 0.05);
                            $rawGainExt = $baseGain + $trainerFactor + $diffFactor;
                        } else {
                            $rawGainExt = 1.0;
                        }

                        // Difficulty factor: 1.0 + (HeroStatExternal / 5)^1.5
                        $difficultyFactor = 1.0 + (($heroStatExt / 5) ** 1.5);

                        // Base gain divided by difficulty
                        $baseGainScaled = $rawGainExt / $difficultyFactor;

                        /** @var \App\Entity\Headquarters\Headquarters|null $hq */
                        $hq = $this->hqRepository->findOneBy(['team' => $hero->getTeam()]);
                        $facilityEfficiency = 0.0;
                        if (null !== $hq) {
                            foreach ($hq->getFacilities() as $fac) {
                                if (\App\Enum\FacilityType::Training === $fac->getType()) {
                                    $bonuses = $fac->getPassiveBonuses();
                                    $facilityEfficiency = ($bonuses['training_efficiency_pct'] ?? 5.0) / 100.0;
                                    break;
                                }
                            }
                        }

                        // Race training speed modifier
                        $raceMod = $this->raceConfig->getTrainingSpeedModifier($hero->getRace());

                        // Total Gain in external scale
                        $finalRawGainExt = $baseGainScaled * (1.0 + $facilityEfficiency) * $raceMod;

                        // Total Gain in internal (raw) scale: round and cap at 9
                        $gainRaw = (int) round($finalRawGainExt * 10);
                        if ($gainRaw < 1) {
                            $gainRaw = 1;
                        }
                        if ($gainRaw > 9) {
                            $gainRaw = 9;
                        }

                        // Cap to Trainer/global limit
                        if ($heroStatRaw + $gainRaw > $cap) {
                            $gainRaw = max(0, $cap - $heroStatRaw);
                        }

                        $this->setHeroRawStatByName($hero, $attribute, $heroStatRaw + $gainRaw);
                        $job->setStatGain($gainRaw);
                    } else {
                        $job->setStatGain(0);
                    }
                } elseif (TrainingType::Magic === $type) {
                    $cap = $hero->getMagicCapacity();
                    if ($cap < 5) {
                        $hero->setMagicCapacity($cap + 1);
                        $job->setStatGain(1);
                    } else {
                        $job->setStatGain(0);
                    }
                } elseif (TrainingType::Form === $type) {
                    $form = $hero->getForm();
                    if ($form < 100) {
                        $recovery = 20;
                        $newForm = min(100, $form + $recovery);
                        $hero->setForm($newForm);
                        $job->setStatGain($newForm - $form);
                    } else {
                        $job->setStatGain(0);
                    }
                }

                $job->setStatus(TrainingStatus::Completed);
                $job->setCompletedAt($now);
            } catch (\Throwable $e) {
                $job->setStatus(TrainingStatus::Pending); // revert status on error
                throw $e;
            }

            // Restore hero status if no other pending jobs
            $pendingCount = $this->queueRepository->countPendingForHero($job->getHero());

            if (0 === $pendingCount) {
                $job->getHero()->setStatus(HeroStatus::Available);
            }
        }

        $this->em->flush();
    }
}
