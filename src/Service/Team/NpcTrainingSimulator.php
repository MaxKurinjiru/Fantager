<?php

declare(strict_types=1);

namespace App\Service\Team;

use App\Entity\Formation\Formation;
use App\Entity\Hero\Hero;
use App\Entity\Item\Item;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;
use App\Enum\HeroRole;
use App\Enum\HeroStatus;
use App\Enum\TrainingType;
use App\Service\Training\TrainingService;
use Doctrine\ORM\EntityManagerInterface;

class NpcTrainingSimulator
{
    use NpcSimulationHelperTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TrainingService $trainingService,
    ) {
    }

    /**
     * Run training simulation: assign trainers and trainees before the weekly training tick.
     */
    public function simulateTraining(Kingdom $kingdom, \DateTimeImmutable $now, ?Team $team = null): void
    {
        if (null !== $team) {
            if (!$team->isNpc()) {
                return;
            }
            $teams = [$team];
        } else {
            $teams = $this->em->getRepository(Team::class)->findBy([
                'kingdom' => $kingdom,
                'isNpc' => true,
            ]);
        }

        foreach ($teams as $team) {
            $teamHeroes = $this->em->getRepository(Hero::class)->findBy([
                'team' => $team,
            ]);

            if (\count($teamHeroes) < 2) {
                continue;
            }

            $trainers = [];
            $trainees = [];
            foreach ($teamHeroes as $h) {
                if ($h->isTrainer()) {
                    $trainers[] = $h;
                } else {
                    $trainees[] = $h;
                }
            }

            // Find active formation hero IDs to avoid promoting combatants in active formations
            $activeFormations = $this->em->getRepository(Formation::class)->findBy(['team' => $team]);
            $activeHeroIds = [];
            foreach ($activeFormations as $form) {
                foreach ($form->getSlots() as $slot) {
                    $heroInSlot = $slot->getHero();
                    if (null !== $heroInSlot) {
                        $heroId = $heroInSlot->getId();
                        if (null !== $heroId) {
                            $activeHeroIds[$heroId] = true;
                        }
                    }
                }
            }

            // Filter available, non-formation combatants for potential promotion
            $promotionCandidates = [];
            foreach ($trainees as $h) {
                $hId = $h->getId();
                if (null === $hId) {
                    continue;
                }
                if (HeroStatus::Available !== $h->getStatus()) {
                    continue;
                }
                if (isset($activeHeroIds[$hId])) {
                    continue;
                }
                $promotionCandidates[] = $h;
            }

            // Sort candidates by age and level descending
            usort($promotionCandidates, function (Hero $a, Hero $b): int {
                $ageCompare = $b->getAgeRaw() <=> $a->getAgeRaw();
                if (0 !== $ageCompare) {
                    return $ageCompare;
                }

                return $b->getLevel() <=> $a->getLevel();
            });

            // Exclude purely negative-trait heroes from trainer promotion:
            // They have no place as trainers either (Slacker/Volatile/Fragile/GlassJaw)
            // unless the team is desperate (no other candidates).
            $promotionCandidatesPositive = array_filter(
                $promotionCandidates,
                fn (Hero $h) => !$this->isPurelyNegativeTrait($h->getTrait())
            );
            if (\count($promotionCandidatesPositive) >= 1) {
                $promotionCandidates = array_values($promotionCandidatesPositive);
            }
            // else: fall back to all candidates (better a Slacker trainer than no trainer)

            $trainerLimit = $this->trainingService->getTrainerLimit($team);
            $needed = $trainerLimit - \count($trainers);
            for ($i = 0; $i < $needed && $i < \count($promotionCandidates); ++$i) {
                $candidate = $promotionCandidates[$i];
                $candidate->setRole(HeroRole::Trainer);
                $trainers[] = $candidate;

                // Unequip all items from the NPC candidate being promoted to trainer
                $equippedItems = $this->em->getRepository(Item::class)->findBy(['equippedHero' => $candidate]);
                foreach ($equippedItems as $item) {
                    $item->setEquippedHero(null);
                    $item->setEquippedSlot(null);
                }

                // Remove from trainees list
                $idx = array_search($candidate, $trainees, true);
                if (false !== $idx) {
                    unset($trainees[$idx]);
                }
            }
            $trainees = array_values($trainees);

            // Reset existing configurations and trainees lists
            foreach ($trainers as $trainer) {
                $trainer->setTrainingType(null);
                $trainer->setTargetAttribute(null);
                $trainer->getTrainees()->clear();
            }
            foreach ($trainees as $trainee) {
                $trainee->setTrainer(null);
            }

            // Sort trainers to ensure the best active trainers are configured/assigned
            usort($trainers, function (Hero $a, Hero $b): int {
                $ageCompare = $b->getAgeRaw() <=> $a->getAgeRaw();
                if (0 !== $ageCompare) {
                    return $ageCompare;
                }

                return $b->getLevel() <=> $a->getLevel();
            });

            $activeTrainers = array_slice($trainers, 0, $trainerLimit);

            $role = $this->getHelperEconomicRole($team);
            $trainingConfigs = $this->getTrainingConfigsForRole($role);

            // Configure active trainers
            foreach ($activeTrainers as $idx => $trainer) {
                $config = $trainingConfigs[$idx % \count($trainingConfigs)];
                $trainer->setTrainingType($config['type']);
                $trainer->setTargetAttribute($config['attribute']);
            }

            // Assign trainees to active trainers up to their slot limits
            // Priority: QuickLearner heroes first (gain more from training), then rest
            usort($trainees, function (Hero $a, Hero $b): int {
                $aIsQuick = \App\Enum\HeroTrait::QuickLearner === $a->getTrait() ? 0 : 1;
                $bIsQuick = \App\Enum\HeroTrait::QuickLearner === $b->getTrait() ? 0 : 1;

                return $aIsQuick <=> $bIsQuick;
            });

            // Assign trainees to active trainers up to their slot limits.
            // Two-pass strategy:
            //   Pass 1 – heroes who can still gain from this trainer's training type/attribute.
            //   Pass 2 – saturated heroes fill any remaining slots so trainer capacity is not wasted.
            // Within each pass, QuickLearner heroes retain priority (pre-sorted above).

            /** @var array<int, int> $trainerSlotsRemaining tracks remaining slot capacity per trainer index */
            $trainerSlotsRemaining = [];
            foreach ($activeTrainers as $tIdx => $trainer) {
                $trainerSlotsRemaining[$tIdx] = $this->trainingService->getTrainerSlotsLimit($trainer);
            }

            $benefiting = [];
            $saturated = [];
            foreach ($trainees as $trainee) {
                // Determine whether this trainee benefits from *any* of the active trainers.
                // We optimistically place them in the benefiting bucket if at least one trainer can help.
                // The actual per-slot check is done during assignment.
                $canBenefit = false;
                foreach ($activeTrainers as $trainer) {
                    if ($this->trainingService->canBenefitFromTraining($trainee, $trainer)) {
                        $canBenefit = true;
                        break;
                    }
                }
                if ($canBenefit) {
                    $benefiting[] = $trainee;
                } else {
                    $saturated[] = $trainee;
                }
            }

            // Pass 1: assign heroes who can benefit
            foreach ($activeTrainers as $tIdx => $trainer) {
                foreach ($benefiting as $bKey => $trainee) {
                    if ($trainerSlotsRemaining[$tIdx] <= 0) {
                        break;
                    }
                    if (HeroStatus::Available !== $trainee->getStatus()) {
                        unset($benefiting[$bKey]);
                        continue;
                    }
                    if (!$this->trainingService->canBenefitFromTraining($trainee, $trainer)) {
                        // This specific trainer can't help this trainee; leave for another trainer
                        continue;
                    }
                    $trainee->setTrainer($trainer);
                    $trainer->addTrainee($trainee);
                    --$trainerSlotsRemaining[$tIdx];
                    unset($benefiting[$bKey]);
                }
            }

            // Pass 2: fill remaining slots with saturated heroes (better than leaving slots empty)
            $saturatedQueue = $saturated;
            $saturatedIndex = 0;
            foreach ($activeTrainers as $tIdx => $trainer) {
                while ($trainerSlotsRemaining[$tIdx] > 0 && $saturatedIndex < \count($saturatedQueue)) {
                    $trainee = $saturatedQueue[$saturatedIndex++];
                    if (HeroStatus::Available !== $trainee->getStatus()) {
                        continue;
                    }
                    $trainee->setTrainer($trainer);
                    $trainer->addTrainee($trainee);
                    --$trainerSlotsRemaining[$tIdx];
                }
            }
        }

        $this->em->flush();
    }

    /**
     * @return array<array{type: TrainingType, attribute: string|null}>
     */
    private function getTrainingConfigsForRole(string $role): array
    {
        return match ($role) {
            NpcSimulationService::ROLE_MERCENARY_ACADEMY => [
                ['type' => TrainingType::Attribute, 'attribute' => 'str'],
                ['type' => TrainingType::Attribute, 'attribute' => 'spd'],
            ],
            NpcSimulationService::ROLE_VETERAN_GUILD => [
                ['type' => TrainingType::Attribute, 'attribute' => 'kon'],
                ['type' => TrainingType::Attribute, 'attribute' => 'wil'],
            ],
            NpcSimulationService::ROLE_ROYAL_COLLECTOR => [
                ['type' => TrainingType::Attribute, 'attribute' => 'int'],
                ['type' => TrainingType::Magic, 'attribute' => null],
            ],
            NpcSimulationService::ROLE_SCAVENGER_CLAN => [
                ['type' => TrainingType::Attribute, 'attribute' => 'spd'],
                ['type' => TrainingType::Attribute, 'attribute' => 'lck'],
            ],
            default => [
                ['type' => TrainingType::Attribute, 'attribute' => 'str'],
                ['type' => TrainingType::Attribute, 'attribute' => 'spd'],
            ],
        };
    }
}
