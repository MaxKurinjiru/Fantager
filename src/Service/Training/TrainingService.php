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
    /** Trainable primary attribute names. */
    private const PRIMARY_ATTRIBUTES = ['str', 'dex', 'kon', 'spd', 'int', 'wil', 'cha', 'lck'];

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

    public function isTrainingLockedForTeam(Team $team, \DateTimeImmutable $now): bool
    {
        $tz = new \DateTimeZone($team->getKingdom()->getTimezone());
        $nowLocal = $now->setTimezone($tz);
        
        $nextTickLocal = $this->getNextTrainingTime($nowLocal);
        $lockStartLocal = $nextTickLocal->modify('-46 hours'); // Wednesday 12:00:00
        
        return $nowLocal >= $lockStartLocal && $nowLocal < $nextTickLocal;
    }

    public function getTrainerLimit(Team $team): int
    {
        /** @var \App\Entity\Headquarters\Headquarters|null $hq */
        $hq = $this->hqRepository->findOneBy(['team' => $team]);
        if (null === $hq) {
            return 2;
        }

        $trainingLevel = 1;
        foreach ($hq->getFacilities() as $facility) {
            if (\App\Enum\FacilityType::Training === $facility->getType()) {
                $trainingLevel = $facility->getLevel();
                break;
            }
        }

        return 2 + (int) floor(($trainingLevel - 1) / 2);
    }

    public function getTrainerSlotsLimit(Trainer $trainer): int
    {
        $team = $trainer->getTeam();
        /** @var \App\Entity\Headquarters\Headquarters|null $hq */
        $hq = $this->hqRepository->findOneBy(['team' => $team]);
        if (null === $hq) {
            return 3;
        }

        $trainingLevel = 1;
        foreach ($hq->getFacilities() as $facility) {
            if (\App\Enum\FacilityType::Training === $facility->getType()) {
                $trainingLevel = $facility->getLevel();
                break;
            }
        }

        return 3 + (int) floor(($trainingLevel - 1) / 2);
    }

    public function configureTrainer(Trainer $trainer, ?TrainingType $type, ?string $attribute, Team $team, \DateTimeImmutable $now): void
    {
        if ($trainer->getTeam()->getId() !== $team->getId()) {
            throw new \DomainException('Trainer does not belong to your team.');
        }

        if ($this->isTrainingLockedForTeam($team, $now)) {
            throw new \DomainException('Training configuration is currently locked.');
        }

        if (null !== $type) {
            if (TrainingType::Attribute === $type) {
                if (null === $attribute || !in_array($attribute, self::PRIMARY_ATTRIBUTES, true)) {
                    throw new \InvalidArgumentException(sprintf('Invalid attribute. Valid values: %s.', implode(', ', self::PRIMARY_ATTRIBUTES)));
                }
            } else {
                $attribute = null; // for magic and form
            }
        } else {
            $attribute = null; // idle
        }

        $trainer->setTrainingType($type);
        $trainer->setTargetAttribute($attribute);
        $this->em->flush();
    }

    public function assignHero(Trainer $trainer, Hero $hero, Team $team, \DateTimeImmutable $now): void
    {
        if ($trainer->getTeam()->getId() !== $team->getId() || $hero->getTeam()->getId() !== $team->getId()) {
            throw new \DomainException('Trainer or Hero does not belong to your team.');
        }

        if ($this->isTrainingLockedForTeam($team, $now)) {
            throw new \DomainException('Training assignments are currently locked.');
        }

        if ($hero->getTrainer() !== null) {
            throw new \DomainException('Hero is already assigned to a trainer.');
        }

        if (\App\Enum\HeroStatus::Dead === $hero->getStatus()) {
            throw new \DomainException('Dead heroes cannot be trained.');
        }

        if (\App\Enum\HeroStatus::Selling === $hero->getStatus()) {
            throw new \DomainException('Listed heroes cannot be trained.');
        }

        if (count($trainer->getHeroes()) >= $this->getTrainerSlotsLimit($trainer)) {
            throw new \DomainException('Trainer does not have any available slots.');
        }

        $trainer->addHero($hero);
        $hero->setStatus(HeroStatus::Training);
        $this->em->flush();
    }

    public function unassignHero(Trainer $trainer, Hero $hero, Team $team, \DateTimeImmutable $now): void
    {
        if ($trainer->getTeam()->getId() !== $team->getId() || $hero->getTeam()->getId() !== $team->getId()) {
            throw new \DomainException('Trainer or Hero does not belong to your team.');
        }

        if ($this->isTrainingLockedForTeam($team, $now)) {
            throw new \DomainException('Training assignments are currently locked.');
        }

        if ($hero->getTrainer()?->getId() !== $trainer->getId()) {
            throw new \DomainException('Hero is not assigned to this trainer.');
        }

        $trainer->removeHero($hero);
        $hero->setStatus(HeroStatus::Available);
        $this->em->flush();
    }

    /**
     * Returns available training options with calculated costs for a hero.
     *
     * @return list<array{type: string, attribute: string|null, execute_at_hours: int}>
     */
    public function getOptions(Hero $hero): array
    {
        // Simple list of attributes and options (free of cost)
        $options = [];
        $tzString = $hero->getTeam()->getKingdom()->getTimezone();
        $tz = new \DateTimeZone($tzString);
        $nowLocal = new \DateTimeImmutable('now', $tz);
        $nextTickLocal = $this->getNextTrainingTime($nowLocal);
        $diffSeconds = $nextTickLocal->getTimestamp() - $nowLocal->getTimestamp();
        $executeAtHours = (int) ceil($diffSeconds / 3600);

        foreach (self::PRIMARY_ATTRIBUTES as $attr) {
            $options[] = [
                'type' => TrainingType::Attribute->value,
                'attribute' => $attr,
                'execute_at_hours' => $executeAtHours,
            ];
        }

        $options[] = [
            'type' => TrainingType::Magic->value,
            'attribute' => null,
            'execute_at_hours' => $executeAtHours,
        ];

        $options[] = [
            'type' => TrainingType::Form->value,
            'attribute' => null,
            'execute_at_hours' => $executeAtHours,
        ];

        return $options;
    }

    /**
     * Process all active trainers and their assigned heroes for weekly training.
     */
    public function processTrainingTick(\DateTimeImmutable $now, ?\App\Entity\Kingdom\Kingdom $kingdom = null): void
    {
        $qb = $this->trainerRepository->createQueryBuilder('t')
            ->join('t.team', 'team')
            ->where('t.trainingType IS NOT NULL');
        if (null !== $kingdom) {
            $qb->andWhere('team.kingdom = :kingdom')
               ->setParameter('kingdom', $kingdom);
        }

        /** @var list<Trainer> $trainers */
        $trainers = $qb->getQuery()->getResult();

        foreach ($trainers as $trainer) {
            $type = $trainer->getTrainingType();
            $attribute = $trainer->getTargetAttribute();

            foreach ($trainer->getHeroes() as $hero) {
                if ($hero->getStatus() === HeroStatus::Dead) {
                    continue;
                }

                $gainRaw = 0;

                if (TrainingType::Attribute === $type && null !== $attribute) {
                    $heroStatExt = $this->getHeroStatByName($hero, $attribute);
                    $heroStatRaw = $this->getHeroRawStatByName($hero, $attribute);
                    $trainerStatExt = $this->getTrainerStatByName($trainer, $attribute);
                    $trainerStatRaw = $this->getTrainerRawStatByName($trainer, $attribute);
                    $cap = $trainerStatRaw;

                    if ($heroStatRaw < $cap) {
                        $baseGain = 1.0;
                        $trainerFactor = max(0.0, ($trainerStatExt - 10) * 0.05);
                        $diffFactor = max(0.0, ($trainerStatExt - $heroStatExt) * 0.05);
                        $rawGainExt = $baseGain + $trainerFactor + $diffFactor;

                        $difficultyFactor = 1.0 + (($heroStatExt / 5) ** 1.5);
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

                        $raceMod = $this->raceConfig->getTrainingSpeedModifier($hero->getRace());
                        $finalRawGainExt = $baseGainScaled * (1.0 + $facilityEfficiency) * $raceMod;

                        $gainRaw = (int) round($finalRawGainExt * 10);
                        $gainRaw = max(1, min(9, $gainRaw));

                        if ($heroStatRaw + $gainRaw > $cap) {
                            $gainRaw = max(0, $cap - $heroStatRaw);
                        }

                        $this->setHeroRawStatByName($hero, $attribute, $heroStatRaw + $gainRaw);
                    }
                    
                    // Standard training adds +20 fatigue (capped at 100)
                    $hero->setFatigue(min(100, $hero->getFatigue() + 20));

                } elseif (TrainingType::Magic === $type) {
                    $cap = $hero->getMagicCapacity();
                    if ($cap < 5) {
                        $hero->setMagicCapacity($cap + 1);
                        $gainRaw = 1;
                    }
                    
                    // Magic training adds +20 fatigue (capped at 100)
                    $hero->setFatigue(min(100, $hero->getFatigue() + 20));

                } elseif (TrainingType::Form === $type) {
                    $form = $hero->getForm();
                    if ($form < 100) {
                        $recovery = 20;
                        $newForm = min(100, $form + $recovery);
                        $hero->setForm($newForm);
                        $gainRaw = $newForm - $form;
                    }
                    
                    // Recovery training reduces fatigue by 20 (floor at 0)
                    $hero->setFatigue(max(0, $hero->getFatigue() - 20));
                }

                // Log a completed training history entry
                $job = new TrainingQueue();
                $job->setHero($hero);
                $job->setTrainingType($type);
                $job->setTargetAttribute($attribute);
                $job->setTrainer($trainer);
                $job->setStatus(TrainingStatus::Completed);
                $job->setStatGain($gainRaw);
                $job->setExecuteAt($now);
                $job->setCompletedAt($now);

                $this->em->persist($job);
            }
        }

        $this->em->flush();
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
}
