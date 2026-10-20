<?php

declare(strict_types=1);

namespace App\Service\Training;

use App\Entity\Hero\Hero;
use App\Entity\Hero\HeroTrainingHistory;
use App\Entity\Item\Item;
use App\Entity\Team\Team;
use App\Enum\FacilityType;
use App\Enum\HeroRole;
use App\Enum\HeroStatus;
use App\Enum\Race;
use App\Enum\School;
use App\Enum\TrainingType;
use App\Exception\UserFacingException;
use App\Repository\Headquarters\HeadquartersRepository;
use App\Repository\Hero\HeroRepository;
use App\Service\Config\RaceConfig;
use App\Service\Economy\FinancialCrisisService;
use App\Service\Hero\HeroChronicleService;
use App\Service\Hero\HeroMasteryService;
use App\Service\Hero\HeroProgressionService;
use App\Service\Kingdom\KingdomPace;
use App\Service\TeamChronicle\TeamChronicleService;
use Doctrine\ORM\EntityManagerInterface;

class TrainingService
{
    /** Trainable primary attribute names. */
    private const PRIMARY_ATTRIBUTES = ['str', 'dex', 'kon', 'spd', 'int', 'wil', 'cha', 'lck'];

    /** School XP from a trainer whose higher of INT and WIL is 10, before pace. */
    public const MAGIC_TRAINING_SCHOOL_XP = 5;

    /** Form points restored by a trainer with KON 10, before training pace. */
    public const FORM_TRAINING_BASE = 20;

    /** Fatigue removed by form training from a KON-10 trainer. Not scaled by training pace. */
    public const FORM_FATIGUE_RELIEF = 20;

    /** Fatigue from attribute training, and from magic training that still raises capacity. */
    public const TRAINING_FATIGUE = 20;

    /** Fatigue from magic training once capacity is already at 5 and only school XP is granted. */
    public const MAGIC_AT_CAP_FATIGUE = 10;

    /**
     * Weeks for a trainer at the race minimum age to reach the mortality threshold
     * at aging pace 1.00, ignoring daily aging. Two seasons (77 days = 11 weeks).
     */
    public const TRAINER_CAREER_WEEKS = 22;

    /** Race relationship treated as neutral for the training-group modifier. */
    private const GROUP_RELATIONSHIP_NEUTRAL = 50;

    /** Each relationship point away from neutral moves the group modifier by this much (50 points = 5%). */
    private const GROUP_RELATIONSHIP_STEP = 0.001;

    public function __construct(
        private readonly HeroRepository $heroRepository,
        private readonly HeadquartersRepository $hqRepository,
        private readonly RaceConfig $raceConfig,
        private readonly TeamChronicleService $teamChronicleService,
        private readonly HeroChronicleService $heroChronicleService,
        private readonly EntityManagerInterface $em,
        private readonly \App\Service\Notification\NotificationHelper $notificationHelper,
        private readonly FinancialCrisisService $financialCrisisService,
        private readonly HeroMasteryService $heroMasteryService,
        private readonly HeroProgressionService $heroProgressionService,
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
        $lockStartLocal = $nextTickLocal->modify('-2 hours'); // Thursday 08:00:00

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
            if (FacilityType::Training === $facility->getType()) {
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
            if (FacilityType::Training === $facility->getType()) {
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
            TrainingType::Magic => null !== $this->resolveTrainingSchool($trainer),
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

        $this->applyTrainerPromotion($hero, $team);
        $this->em->flush();
    }

    /**
     * Shared logic for promoting a hero to trainer role.
     * Sets the role, clears training config, unequips items, removes from formations, and writes the team chronicle.
     *
     * Does NOT perform any validation guards and does NOT call em->flush().
     * The caller is responsible for flushing after this method returns.
     *
     * Used by both the player-facing promoteToTrainer() (which adds validation guards + flush)
     * and NPC simulation paths (NpcTrainingSimulator, NpcEconomySimulator).
     */
    public function applyTrainerPromotion(Hero $hero, Team $team): void
    {
        $hero->setRole(HeroRole::Trainer);
        $hero->setTrainingType(null);
        $hero->setTargetAttribute(null);
        $hero->setMagicCapacity(0);

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

        $this->teamChronicleService->recordTrainerPromoted($team, $hero);
    }

    public function configureTrainer(Hero $trainer, ?TrainingType $type, ?string $attribute, Team $team, \DateTimeImmutable $now): void
    {
        if (!$trainer->isTrainer()) {
            throw new UserFacingException('error.trainer_not_entity');
        }

        if ($trainer->getTeam()->getId() !== $team->getId()) {
            throw new UserFacingException('error.trainer_not_on_team');
        }

        if (HeroStatus::Available !== $trainer->getStatus()) {
            throw new UserFacingException('error.trainer_only_active_configure');
        }

        if ($this->isTrainingLockedForTeam($team, $now)) {
            throw new UserFacingException('error.trainer_config_locked');
        }

        if (null !== $type) {
            if (TrainingType::Attribute === $type) {
                if (null === $attribute || !in_array($attribute, self::PRIMARY_ATTRIBUTES, true)) {
                    throw new UserFacingException('error.invalid_attribute', ['%values%' => implode(', ', self::PRIMARY_ATTRIBUTES)]);
                }
            } elseif (TrainingType::Magic === $type) {
                if (null === $attribute || null === School::tryFrom($attribute)) {
                    $schools = array_map(static fn (School $school): string => $school->value, School::cases());
                    throw new UserFacingException('error.invalid_school', ['%values%' => implode(', ', $schools)]);
                }
            } else {
                $attribute = null; // form
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

        if (HeroStatus::Available !== $trainer->getStatus()) {
            throw new UserFacingException('error.trainer_only_active_assign');
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

        if (HeroStatus::Available !== $trainer->getStatus()) {
            throw new UserFacingException('error.trainer_only_active_unassign');
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

        $notifiedTeamIds = [];

        foreach ($trainers as $trainer) {
            $team = $trainer->getTeam();
            $teamId = $team->getId();

            if (null !== $teamId && !in_array($teamId, $notifiedTeamIds, true)) {
                $notifiedTeamIds[] = $teamId;
                if (null !== $team->getUser()) {
                    $this->notificationHelper->sendTranslatedNotification(
                        $team->getUser(),
                        \App\Enum\NotificationType::TrainingComplete,
                        'notification.training_completed_title',
                        'notification.training_completed_body'
                    );
                }
            }

            $kingdom = $trainer->getTeam()->getKingdom();
            $speed = KingdomPace::training($kingdom);

            $trainees = [];
            foreach ($trainer->getTrainees() as $hero) {
                if (HeroStatus::Dead !== $hero->getStatus()) {
                    $trainees[] = $hero;
                }
            }

            // A configured trainer with nobody to teach does not take the weekly age jump.
            if ([] !== $trainees) {
                $trainerAgeIncrement = $this->trainerWeeklyAgeRaw($trainer);
                if ($trainerAgeIncrement > 0) {
                    $trainer->setAgeRaw($trainer->getAgeRaw() + $trainerAgeIncrement);
                }
            }

            $type = $trainer->getTrainingType();
            /** @var TrainingType $type — only trainers with non-null type are queried */
            $attribute = $trainer->getTargetAttribute();
            foreach ($trainees as $hero) {
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
                        if (null !== $hq && $this->financialCrisisService->areHqBonusesActive($hero->getTeam())) {
                            foreach ($hq->getFacilities() as $fac) {
                                if (FacilityType::Training === $fac->getType()) {
                                    $bonuses = $fac->getPassiveBonuses();
                                    $facilityEfficiency = ($bonuses['training_efficiency_pct'] ?? 3.0) / 100.0;
                                    break;
                                }
                            }
                        }

                        $raceMod = $this->raceConfig->getTrainingSpeedModifier($hero->getRace());

                        $arenaBonus = 1.0;
                        if (null !== $hq && null !== $hq->getRaceOptimization()) {
                            $adaptedRace = Race::tryFrom($hq->getRaceOptimization());
                            if (null !== $adaptedRace) {
                                if ($hero->getRace() === $adaptedRace) {
                                    $arenaBonus = 1.10;
                                } elseif ($this->raceConfig->getRelationship($hero->getRace(), $adaptedRace) >= 70) {
                                    $arenaBonus = 1.05;
                                }
                            }
                        }

                        $groupMod = $this->groupModifier($hero, $trainees);

                        $finalRawGainExt = $baseGainScaled * (1.0 + $facilityEfficiency) * $raceMod * $arenaBonus * $groupMod * $speed;

                        // Trait modifier: QuickLearner +20 %, Slacker -15 %, Perfectionist -10 %
                        $traitMult = $hero->getTrait()?->getTrainingSpeedMultiplier() ?? 1.0;
                        $finalRawGainExt *= $traitMult;

                        $gainRaw = (int) round($finalRawGainExt * 10);
                        $gainRaw = max(1, min($this->attributeGainCapRaw($trainerStatExt, $speed), $gainRaw));

                        if ($heroStatRaw + $gainRaw > $cap) {
                            $gainRaw = max(0, $cap - $heroStatRaw);
                        }

                        $this->setHeroRawStatByName($hero, $attribute, $heroStatRaw + $gainRaw);
                        $gainExt = (int) floor(($heroStatRaw + $gainRaw) / 10) - (int) floor($heroStatRaw / 10);
                    }

                    // Fatigue only when the hero actually gained (not when already at trainer cap)
                    if ($gainRaw > 0) {
                        $hero->setFatigue(min(100, $hero->getFatigue() + self::TRAINING_FATIGUE));
                    }
                } elseif (TrainingType::Magic === $type) {
                    $school = $this->resolveTrainingSchool($trainer);
                    $cap = $hero->getMagicCapacity();
                    if ($cap < 5) {
                        $magicIncrement = (int) max(1, round(1 * $speed));
                        $newCap = min(5, $cap + $magicIncrement);
                        $hero->setMagicCapacity($newCap);
                        $gainRaw = $newCap - $cap;
                    }

                    if (null !== $school) {
                        $this->heroMasteryService->recordSchoolExercise($hero, $school, $this->magicSchoolXp($trainer), $now);
                    }

                    $fatigueCost = $this->magicFatigueCost($gainRaw > 0, null !== $school);
                    if ($fatigueCost > 0) {
                        $hero->setFatigue(min(100, $hero->getFatigue() + $fatigueCost));
                    }
                } elseif (TrainingType::Form === $type) {
                    $form = $hero->getForm();
                    if ($form < 100) {
                        $recovery = $this->formRecoveryAmount($trainer);
                        $newForm = min(100, $form + $recovery);
                        $hero->setForm($newForm);
                        $gainRaw = $newForm - $form;
                    }

                    $hero->setFatigue(max(0, $hero->getFatigue() - $this->formFatigueRelief($trainer)));
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

                $this->heroProgressionService->applyTrainingParticipation(
                    $hero,
                    $hero->getTeam(),
                    $this->libraryXpMultiplier($hero->getTeam()),
                );
            }
        }

        $this->em->flush();
    }

    /**
     * Raw tenths added on the training tick. Scales with the race's span from minimum age
     * to the mortality threshold, so a trainer promoted young has about TRAINER_CAREER_WEEKS
     * of these jumps at aging pace 1.00. Returns 0 when the span is empty.
     */
    public function trainerWeeklyAgeRaw(Hero $trainer): int
    {
        $aging = KingdomPace::aging($trainer->getTeam()->getKingdom());
        $milestones = $this->raceConfig->getAge($trainer->getRace());
        $spanYears = $milestones['mortality_threshold'] - $milestones['min'];
        if ($spanYears <= 0) {
            return 0;
        }

        return max(0, (int) round(($spanYears / self::TRAINER_CAREER_WEEKS) * $aging * 10));
    }

    /**
     * Calendar weeks until the mortality threshold, counting the weekly trainer jump
     * and, for races that age daily, seven daily increments. Undead skip the daily part.
     */
    public function trainerWeeksUntilMortality(Hero $trainer): int
    {
        $perWeek = $this->trainerWeeklyAgeRaw($trainer);
        if (Race::Undead !== $trainer->getRace()) {
            $perWeek += (int) round(KingdomPace::aging($trainer->getTeam()->getKingdom())) * 7;
        }
        if ($perWeek <= 0) {
            return 0;
        }

        $thresholdRaw = $this->raceConfig->getAge($trainer->getRace())['mortality_threshold'] * 10;
        $remaining = $thresholdRaw - $trainer->getAgeRaw();
        if ($remaining <= 0) {
            return 0;
        }

        return (int) ceil($remaining / $perWeek);
    }

    public function projectedFatigueDelta(Hero $trainer, Hero $hero): int
    {
        $type = $trainer->getTrainingType();
        if (null === $type) {
            return 0;
        }

        return match ($type) {
            TrainingType::Form => -$this->formFatigueRelief($trainer),
            TrainingType::Magic => $this->magicFatigueCost(
                $hero->getMagicCapacity() < 5,
                null !== $this->resolveTrainingSchool($trainer),
            ),
            TrainingType::Attribute => $this->canBenefitFromAttributeTraining($hero, $trainer) ? self::TRAINING_FATIGUE : 0,
        };
    }

    public function projectedFormGain(Hero $trainer, Hero $hero): int
    {
        if (TrainingType::Form !== $trainer->getTrainingType() || $hero->getForm() >= 100) {
            return 0;
        }

        return min(100 - $hero->getForm(), $this->formRecoveryAmount($trainer));
    }

    private function attributeGainCapRaw(int $trainerStatExternal, float $speed): int
    {
        $cap = 9 + max(0, $trainerStatExternal - 10);

        return max(1, (int) round($cap * $speed));
    }

    /**
     * @param list<Hero> $trainees living trainees of this trainer, including $hero
     */
    private function groupModifier(Hero $hero, array $trainees): float
    {
        $sum = 0;
        $count = 0;
        foreach ($trainees as $other) {
            if ($other === $hero) {
                continue;
            }
            $sum += $this->raceConfig->getRelationship($hero->getRace(), $other->getRace());
            ++$count;
        }
        if (0 === $count) {
            return 1.0;
        }

        $average = $sum / $count;

        return 1.0 + (($average - self::GROUP_RELATIONSHIP_NEUTRAL) * self::GROUP_RELATIONSHIP_STEP);
    }

    private function trainerStatFactor(int $externalStat): float
    {
        return max(0, $externalStat) / 10;
    }

    private function magicSchoolXp(Hero $trainer): int
    {
        $speed = KingdomPace::training($trainer->getTeam()->getKingdom());
        $stat = max($trainer->getIntel(), $trainer->getWil());

        return max(1, (int) round(self::MAGIC_TRAINING_SCHOOL_XP * $this->trainerStatFactor($stat) * $speed));
    }

    private function magicFatigueCost(bool $capacityIncreased, bool $schoolGranted): int
    {
        if ($capacityIncreased) {
            return self::TRAINING_FATIGUE;
        }
        if ($schoolGranted) {
            return self::MAGIC_AT_CAP_FATIGUE;
        }

        return 0;
    }

    private function formRecoveryAmount(Hero $trainer): int
    {
        $speed = KingdomPace::training($trainer->getTeam()->getKingdom());

        return (int) round(self::FORM_TRAINING_BASE * $speed * $this->trainerStatFactor($trainer->getKon()));
    }

    private function formFatigueRelief(Hero $trainer): int
    {
        return (int) round(self::FORM_FATIGUE_RELIEF * $this->trainerStatFactor($trainer->getKon()));
    }

    private function resolveTrainingSchool(Hero $trainer): ?School
    {
        $raw = $trainer->getTargetAttribute();
        if (null === $raw) {
            return null;
        }

        return School::tryFrom($raw);
    }

    private function libraryXpMultiplier(Team $team): float
    {
        $hq = $this->hqRepository->findOneBy(['team' => $team]);
        if (null === $hq || !$this->financialCrisisService->areHqBonusesActive($team)) {
            return 1.0;
        }

        foreach ($hq->getFacilities() as $facility) {
            if (FacilityType::Library === $facility->getType()) {
                $pct = (float) ($facility->getPassiveBonuses()['xp_gain_pct'] ?? 0);

                return 1.0 + ($pct / 100.0);
            }
        }

        return 1.0;
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
