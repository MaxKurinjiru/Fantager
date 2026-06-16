<?php

declare(strict_types=1);

namespace App\Service\TeamChronicle;

use App\Entity\Auth\User;
use App\Entity\Hero\Hero;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;
use App\Entity\Team\TeamChronicle;
use App\Enum\ChronicleEventType;
use App\Enum\ChronicleReleaseReason;
use App\Enum\Race;
use Doctrine\ORM\EntityManagerInterface;

class TeamChronicleService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function recordTeamEstablished(Team $team, Kingdom $kingdom, int $seasonNumber): TeamChronicle
    {
        return $this->create(
            $team,
            ChronicleEventType::TeamEstablished,
            'activity.team_established',
            [
                'kingdom' => $kingdom->getName(),
                'season' => (string) $seasonNumber,
            ],
            [
                'kingdom_id' => $kingdom->getId(),
                'season' => $seasonNumber,
            ],
        );
    }

    public function recordPlayerJoined(Team $team, User $user): TeamChronicle
    {
        return $this->create(
            $team,
            ChronicleEventType::PlayerJoined,
            'activity.player_joined',
            [
                'player' => $user->getDisplayName(),
            ],
            [
                'user_id' => $user->getId(),
            ],
        );
    }

    public function recordPlayerReleased(Team $team, ?User $user, ChronicleReleaseReason $reason): TeamChronicle
    {
        return $this->create(
            $team,
            ChronicleEventType::PlayerReleased,
            'activity.player_released.'.$reason->value,
            [
                'player' => $user?->getDisplayName() ?? '—',
            ],
            [
                'user_id' => $user?->getId(),
                'reason' => $reason->value,
            ],
        );
    }

    public function recordSeasonEnded(
        Team $team,
        int $seasonNumber,
        string $tierName,
        int $position,
        string $status,
        int $goldGranted,
    ): TeamChronicle {
        return $this->create(
            $team,
            ChronicleEventType::SeasonEnded,
            'activity.season_ended',
            [
                'season' => (string) $seasonNumber,
                'tier' => $tierName,
                'position' => (string) $position,
                'status' => '' !== $status ? $status : 'maintained',
            ],
            [
                'gold' => $goldGranted,
                'status' => $status,
            ],
        );
    }

    public function recordSummonCompleted(Team $team, Hero $hero, Race $race, int $goldCost): TeamChronicle
    {
        return $this->create(
            $team,
            ChronicleEventType::SummonCompleted,
            'activity.summon_completed',
            [
                'hero' => $hero->getName(),
                'race' => $race->value,
            ],
            [
                'hero_id' => $hero->getId(),
                'race' => $race->value,
                'gold_cost' => $goldCost,
            ],
        );
    }

    /**
     * @param array<string, string> $subjectParams
     * @param array<string, mixed>  $data
     */
    private function create(
        Team $team,
        ChronicleEventType $type,
        string $subjectKey,
        array $subjectParams,
        array $data = [],
    ): TeamChronicle {
        $entry = new TeamChronicle();
        $entry->setTeam($team);
        $entry->setType($type);
        $entry->setSubjectKey($subjectKey);
        $entry->setSubjectParams($subjectParams);
        $entry->setData($data);

        $this->em->persist($entry);

        return $entry;
    }
}
