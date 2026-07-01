<?php

declare(strict_types=1);

namespace App\Service\Training;

use App\Entity\Hero\Hero;
use App\Entity\Hero\HeroTrainingHistory;
use App\Entity\Item\Item;
use App\Entity\Team\Team;
use App\Enum\HeroRole;
use App\Enum\HeroStatus;
use App\Enum\TrainingType;
use App\Exception\UserFacingException;
use App\Repository\Headquarters\HeadquartersRepository;
use App\Repository\Hero\HeroRepository;
use App\Service\Config\RaceConfig;
use App\Service\Hero\HeroChronicleService;
use App\Service\TeamChronicle\TeamChronicleService;
use Doctrine\ORM\EntityManagerInterface;

class TrainingService
{
    /** Trainable primary attribute names. */
    private const PRIMARY_ATTRIBUTES = ['str', 'dex', 'kon', 'spd', 'int', 'wil', 'cha', 'lck'];

    public function __construct(
        private readonly HeroRepository $heroRepository,
        private readonly HeadquartersRepository $hqRepository,
        private readonly RaceConfig $raceConfig,
        private readonly TeamChronicleService $teamChronicleService,
        private readonly HeroChronicleService $heroChronicleService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function getNextTrainingTime(\DateTimeImmutable $now): \DateTimeImmutable
    {
        $thursdayThisWeek = $now->modify('this week Thursday 10:00:00');
        if ($now < $thursdayThisWeek) {
            return $thursdayThisWeek;
        }

        return $now->modify('next week Thursday 10:00:00');
    }

    public function isTrainingLockedForTeam(Team $team, \DateTimeImmutable $now): bool
    {
        $tz = new \DateTimeZone($team->getKingdom()->getTimezone());
        $nowLocal = $now->setTimezone($tz);

        $nextTickLocal = $this->getNextTrainingTime($nowLocal);
        $lockStartLocal = $nextTickLocal->modify('-46 hours'); // Tuesday 12:00:00

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

    public function getTrainerSlotsLimit(Hero $trainer): int
    {
        if (!$trainer->isTrainer()) {
            throw new UserFacingException('error.trainer_not_entity');
        }

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

    /**
     * Returns true if the trainee can gain something from the trainer's current training configuration.
     * Used by NPC simulation to skip saturated heroes and give the slot to a hero who can still improve.
     */
    public function canBenefitFromTraining(Hero $trainee, Hero $trainer): bool
    {
        $type = $trainer->getTrainingType();
        if (null === $type) {
            return false;
        }

        return match ($type) {
            TrainingType::Attribute => $this->canBenefitFromAttributeTraining($trainee, $trainer),
            TrainingType::Magic => $trainee->getMagicCapacity() < 5,
            TrainingType::Form => $trainee->getForm() < 100,
        };
    }

    private function canBenefitFromAttributeTraining(Hero $trainee, Hero $trainer): bool
    {
        $attribute = $trainer->getTargetAttribute();
        if (null === $attribute) {
            return false;
        }

        try {
            $heroStatRaw = $this->getHeroRawStatByName($trainee, $attribute);
            $trainerStatRaw = $this->getHeroRawStatByName($trainer, $attribute);
        } catch (\Throwable) {
            return false;
        }

        // Hero is saturated when their raw stat has already reached the trainer's raw stat cap
        return $heroStatRaw < $trainerStatRaw;
    }

    public function promoteToTrainer(Hero $hero, Team $team, \DateTimeImmutable $now): void
    {
        if ($this->isTrainingLockedForTeam($team, $now)) {
            throw new UserFacingException('error.trainer_config_locked');
        }

        if ($hero->getTeam()->getId() !== $team->getId()) {
            throw new UserFacingException('error.trainer_hero_not_on_team');
        }

        if (!$hero->isCombatant()) {
            throw new UserFacingException('error.hero_already_trainer');
        }

        if (HeroStatus::Available !== $hero->getStatus()) {
            throw new UserFacingException('error.hero_only_available_roster');
        }

        $trainersCount = $this->heroRepository->countTrainersByTeam($team);
        $trainerLimit = $this->getTrainerLimit($team);
        if ($trainersCount >= $trainerLimit) {
            throw new UserFacingException('error.training_trainer_limit_reached');
        }

        $hero->setRole(HeroRole::Trainer);
        $hero->setTrainingType(null);
        $hero->setTargetAttribute(null);

        // Unequip all items from the hero being promoted to trainer
        $equippedItems = $this->em->getRepository(Item::class)->findBy(['equippedHero' => $hero]);
        foreach ($equippedItems as $item) {
            $item->setEquippedHero(null);
            $item->setEquippedSlot(null);
        }

        // Remove hero from active formations
        /** @var list<\App\Entity\Formation\FormationSlot> $slots */
        $slots = $this->em->getRepository(\App\Entity\Formation\FormationSlot::class)->findBy(['hero' => $hero]);
        foreach ($slots as $slot) {
            $slot->setHero(null);
        }

        $this->em->flush();
    }

    public function configureTrainer(Hero $trainer, ?TrainingType $type, ?string $attribute, Team $team, \DateTimeImmutable $now): void
    {
        if (!$trainer->isTrainer()) {
            throw new UserFacingException('error.trainer_not_entity');
        }

        if ($trainer->getTeam()->getId() !== $team->getId()) {
            throw new UserFacingException('error.trainer_not_on_team');
        }

        if ($this->isTrainingLockedForTeam($team, $now)) {
            throw new UserFacingException('error.trainer_config_locked');
        }

        if (null !== $type) {
            if (TrainingType::Attribute === $type) {
                if (null === $attribute || !in_array($attribute, self::PRIMARY_ATTRIBUTES, true)) {
                    throw new UserFacingException('error.invalid_attribute', ['%values%' => implode(', ', self::PRIMARY_ATTRIBUTES)]);
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

    public function assignHero(Hero $trainer, Hero $hero, Team $team, \DateTimeImmutable $now): void
    {
        if (!$trainer->isTrainer()) {
            throw new UserFacingException('error.trainer_not_entity');
        }

        if (!$hero->isCombatant()) {
            throw new UserFacingException('error.trainer_only_combatant_assign');
        }

        if ($trainer->getTeam()->getId() !== $team->getId() || $hero->getTeam()->getId() !== $team->getId()) {
            throw new UserFacingException('error.trainer_hero_not_on_team');
        }

        if ($this->isTrainingLockedForTeam($team, $now)) {
            throw new UserFacingException('error.trainer_assignments_locked');
        }

        if (null !== $hero->getTrainer()) {
            throw new UserFacingException('error.trainer_hero_already_assigned');
        }

        if (HeroStatus::Available !== $hero->getStatus()) {
            throw new UserFacingException('error.trainer_hero_only_available');
        }

        if (count($trainer->getTrainees()) >= $this->getTrainerSlotsLimit($trainer)) {
            throw new UserFacingException('error.trainer_no_slots');
        }

        $trainer->addTrainee($hero);
        $this->em->flush();
    }

    public function unassignHero(Hero $trainer, Hero $hero, Team $team, \DateTimeImmutable $now): void
    {
        if (!$trainer->isTrainer()) {
            throw new UserFacingException('error.trainer_not_entity');
        }

        if ($trainer->getTeam()->getId() !== $team->getId() || $hero->getTeam()->getId() !== $team->getId()) {
            throw new UserFacingException('error.trainer_hero_not_on_team');
        }

        if ($this->isTrainingLockedForTeam($team, $now)) {
            throw new UserFacingException('error.trainer_assignments_locked');
        }

        if ($hero->getTrainer()?->getId() !== $trainer->getId()) {
            throw new UserFacingException('error.trainer_hero_not_assigned');
        }

        $trainer->removeTrainee($hero);
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
    public function processTrainingTick(\DateTimeImmutable $now, ?\App\Entity\Kingdom\Kingdom $kingdom = null, ?Team $team = null): void
    {
        $qb = $this->heroRepository->createQueryBuilder('h')
            ->join('h.team', 'team')
            ->where('h.role = :trainerRole')
            ->andWhere('h.trainingType IS NOT NULL')
            ->setParameter('trainerRole', HeroRole::Trainer);
        if (null !== $team) {
            $qb->andWhere('h.team = :team')
               ->setParameter('team', $team);
        } elseif (null !== $kingdom) {
            $qb->andWhere('team.kingdom = :kingdom')
               ->setParameter('kingdom', $kingdom);
        }

        /** @var list<Hero> $trainers */
        $trainers = $qb->getQuery()->getResult();

        foreach ($trainers as $trainer) {
            // Active trainers age by a stronger jump during the training tick (combat death equivalent)
            $speed = (float) $trainer->getTeam()->getKingdom()->getGameSpeed();
            if ($speed <= 0.0) {
                $speed = 1.0;
            }
            $trainerAgeIncrement = (int) round(10 * $speed);
            if ($trainerAgeIncrement > 0) {
                $trainer->setAgeRaw($trainer->getAgeRaw() + $trainerAgeIncrement);
            }

            $type = $trainer->getTrainingType();
            /** @var TrainingType $type — only trainers with non-null type are queried */
            $attribute = $trainer->getTargetAttribute();
            foreach ($trainer->getTrainees() as $hero) {
                if (HeroStatus::Dead === $hero->getStatus()) {
                    continue;
                }

                $gainRaw = 0;
                $gainExt = 0;

                if (TrainingType::Attribute === $type && null !== $attribute) {
                    $heroStatExt = $this->getHeroStatByName($hero, $attribute);
                    $heroStatRaw = $this->getHeroRawStatByName($hero, $attribute);
                    $trainerStatExt = $this->getHeroStatByName($trainer, $attribute);
                    $trainerStatRaw = $this->getHeroRawStatByName($trainer, $attribute);
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
                        $finalRawGainExt = $baseGainScaled * (1.0 + $facilityEfficiency) * $raceMod * $speed;

                        // Trait modifier: QuickLearner +20 %, Slacker -15 %, Perfectionist -10 %
                        $traitMult = $hero->getTrait()?->getTrainingSpeedMultiplier() ?? 1.0;
                        $finalRawGainExt *= $traitMult;

                        $gainRaw = (int) round($finalRawGainExt * 10);
                        $gainRaw = max(1, min((int) round(9 * $speed), $gainRaw));

                        if ($heroStatRaw + $gainRaw > $cap) {
                            $gainRaw = max(0, $cap - $heroStatRaw);
                        }

                        $this->setHeroRawStatByName($hero, $attribute, $heroStatRaw + $gainRaw);
                        $gainExt = (int) floor(($heroStatRaw + $gainRaw) / 10) - (int) floor($heroStatRaw / 10);
                    }

                    // Standard training adds +20 fatigue (capped at 100)
                    $hero->setFatigue(min(100, $hero->getFatigue() + 20));
                } elseif (TrainingType::Magic === $type) {
                    $cap = $hero->getMagicCapacity();
                    if ($cap < 5) {
                        $magicIncrement = (int) max(1, round(1 * $speed));
                        $newCap = min(5, $cap + $magicIncrement);
                        $hero->setMagicCapacity($newCap);
                        $gainRaw = $newCap - $cap;
                    }

                    // Magic training adds +20 fatigue (capped at 100)
                    $hero->setFatigue(min(100, $hero->getFatigue() + 20));
                } elseif (TrainingType::Form === $type) {
                    $form = $hero->getForm();
                    if ($form < 100) {
                        $recovery = (int) round(20 * $speed);
                        $newForm = min(100, $form + $recovery);
                        $hero->setForm($newForm);
                        $gainRaw = $newForm - $form;
                    }

                    // Recovery training reduces fatigue by 20 (floor at 0)
                    $hero->setFatigue(max(0, $hero->getFatigue() - 20));
                }

                $history = new HeroTrainingHistory();
                $history->setHero($hero);
                $history->setTrainingType($type);
                $history->setTargetAttribute($attribute);
                $history->setTrainer($trainer);
                $history->setStatGain(TrainingType::Attribute === $type ? $gainExt : $gainRaw);
                $history->setCompletedAt($now);

                $this->em->persist($history);

                $this->teamChronicleService->recordTrainingCompleted(
                    $hero->getTeam(),
                    $hero,
                    $trainer,
                    $type->value,
                    $attribute,
                    TrainingType::Attribute === $type ? $gainExt : $gainRaw
                );

                $this->heroChronicleService->recordTrainingCompleted(
                    $hero,
                    $trainer,
                    $type->value,
                    $attribute,
                    TrainingType::Attribute === $type ? $gainExt : $gainRaw
                );
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
            default => throw new UserFacingException('error.unknown_attribute', ['%attribute%' => $attr]),
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
            default => throw new UserFacingException('error.unknown_attribute', ['%attribute%' => $attr]),
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
            default: throw new UserFacingException('error.unknown_attribute', ['%attribute%' => $attr]);
        }
    }
}
