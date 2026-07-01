<?php

declare(strict_types=1);

namespace App\Service\Hero;

use App\Entity\Hero\Hero;
use App\Entity\Hero\HeroChronicle;
use App\Entity\Team\Team;
use App\Enum\HeroChronicleEventType;
use App\Service\Calendar\TickClock;
use Doctrine\ORM\EntityManagerInterface;

class HeroChronicleService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TickClock $tickClock,
    ) {
    }

    public function recordSummoned(Hero $hero, int $goldCost): HeroChronicle
    {
        return $this->create(
            $hero,
            $hero->getTeam(),
            HeroChronicleEventType::Summoned,
            'hero_activity.summoned',
            [
                'team' => $hero->getTeam()->getName(),
                'gold' => (string) $goldCost,
            ],
            [
                'gold_cost' => $goldCost,
            ]
        );
    }

    public function recordJoinedStartingRoster(Hero $hero, ?\DateTimeImmutable $createdAt = null): HeroChronicle
    {
        return $this->create(
            $hero,
            $hero->getTeam(),
            HeroChronicleEventType::Summoned,
            'hero_activity.starting_roster',
            [
                'team' => $hero->getTeam()->getName(),
            ],
            [],
            $createdAt
        );
    }

    public function recordTransferred(Hero $hero, Team $from, Team $to, int $price): HeroChronicle
    {
        return $this->create(
            $hero,
            $to,
            HeroChronicleEventType::Transferred,
            'hero_activity.transferred',
            [
                'from_team' => $from->getName(),
                'to_team' => $to->getName(),
                'price' => (string) $price,
            ],
            [
                'from_team_id' => $from->getId(),
                'to_team_id' => $to->getId(),
                'price' => $price,
            ]
        );
    }

    public function recordMatchPlayed(Hero $hero, Team $opponent, string $result, int $kills): HeroChronicle
    {
        return $this->create(
            $hero,
            $hero->getTeam(),
            HeroChronicleEventType::MatchPlayed,
            'hero_activity.match_played.'.$result,
            [
                'opponent' => $opponent->getName(),
                'kills' => (string) $kills,
            ],
            [
                'opponent_team_id' => $opponent->getId(),
                'result' => $result,
                'kills' => $kills,
            ]
        );
    }

    public function recordLevelUp(Hero $hero, int $oldLevel, int $newLevel): HeroChronicle
    {
        return $this->create(
            $hero,
            $hero->getTeam(),
            HeroChronicleEventType::LevelUp,
            'hero_activity.levelup',
            [
                'old_level' => (string) $oldLevel,
                'new_level' => (string) $newLevel,
            ],
            [
                'old_level' => $oldLevel,
                'new_level' => $newLevel,
            ]
        );
    }

    public function recordMasteryGained(Hero $hero, string $masteryName, int $newTier): HeroChronicle
    {
        return $this->create(
            $hero,
            $hero->getTeam(),
            HeroChronicleEventType::MasteryGained,
            'hero_activity.mastery_gained',
            [
                'mastery' => $masteryName,
                'tier' => (string) $newTier,
            ],
            [
                'mastery' => $masteryName,
                'tier' => $newTier,
            ]
        );
    }

    public function recordTrainingCompleted(
        Hero $hero,
        Hero $trainer,
        string $trainingType,
        ?string $attribute,
        int $gain,
    ): HeroChronicle {
        $gainFormatted = '+'.$gain;

        return $this->create(
            $hero,
            $hero->getTeam(),
            HeroChronicleEventType::TrainingCompleted,
            'hero_activity.training_completed.'.$trainingType,
            [
                'trainer' => $trainer->getName(),
                'attribute' => $attribute ?? '',
                'gain' => $gainFormatted,
            ],
            [
                'trainer_id' => $trainer->getId(),
                'type' => $trainingType,
                'attribute' => $attribute,
                'gain_raw' => $gain,
            ]
        );
    }

    public function recordDied(Hero $hero, string $cause): HeroChronicle
    {
        return $this->create(
            $hero,
            $hero->getTeam(),
            HeroChronicleEventType::Died,
            'hero_activity.died.'.$cause,
            [],
            [
                'cause' => $cause,
            ]
        );
    }

    /**
     * @param array<string, string> $subjectParams
     * @param array<string, mixed>  $data
     */
    private function create(
        Hero $hero,
        ?Team $team,
        HeroChronicleEventType $type,
        string $subjectKey,
        array $subjectParams,
        array $data = [],
        ?\DateTimeImmutable $createdAt = null,
    ): HeroChronicle {
        $entry = new HeroChronicle();
        $entry->setHero($hero);
        $entry->setOriginalHeroId($hero->getId());
        $entry->setTeam($team);
        $entry->setType($type);
        $entry->setSubjectKey($subjectKey);
        $entry->setSubjectParams($subjectParams);
        $entry->setData($data);
        $entry->setCreatedAt($createdAt ?? $this->tickClock->getCurrentTime());

        $this->em->persist($entry);

        return $entry;
    }
}
