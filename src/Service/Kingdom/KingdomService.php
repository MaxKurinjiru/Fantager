<?php

declare(strict_types=1);

namespace App\Service\Kingdom;

use App\Entity\Kingdom\Kingdom;
use App\Enum\LeagueSeasonStatus;
use App\Repository\Kingdom\KingdomRepository;
use App\Repository\League\LeagueSeasonRepository;
use App\Repository\Team\TeamRepository;

class KingdomService
{
    public function __construct(
        private readonly KingdomRepository $kingdomRepository,
        private readonly TeamRepository $teamRepository,
        private readonly LeagueSeasonRepository $seasonRepository,
    ) {
    }

    /** @return list<array{kingdom: Kingdom, capacity: int, playerCount: int}> */
    public function listWithCapacity(): array
    {
        $kingdoms = $this->kingdomRepository->findAll();
        $result = [];

        foreach ($kingdoms as $kingdom) {
            if (!$this->hasActiveSeason($kingdom)) {
                continue;
            }
            $result[] = [
                'kingdom' => $kingdom,
                'capacity' => $this->calculateCapacity($kingdom),
                'playerCount' => $this->teamRepository->countPlayersByKingdom((int) $kingdom->getId()),
            ];
        }

        return $result;
    }

    public function calculateCapacity(Kingdom $kingdom): int
    {
        $config = $kingdom->getLeagueTiersConfig();
        $teamsPerGroup = (int) ($config['teams_per_group'] ?? 0);
        /** @var list<array<string, mixed>> $tiers */
        $tiers = $config['tiers'] ?? [];

        $capacity = 0;
        foreach ($tiers as $tier) {
            $groupsDef = $tier['groups'] ?? 0;
            $groupCount = is_array($groupsDef) ? count($groupsDef) : (int) $groupsDef;
            $capacity += $groupCount * $teamsPerGroup;
        }

        return $capacity;
    }

    public function hasCapacity(Kingdom $kingdom): bool
    {
        $capacity = $this->calculateCapacity($kingdom);
        if (0 === $capacity) {
            return true; // no config yet — allow
        }

        return $this->teamRepository->countPlayersByKingdom((int) $kingdom->getId()) < $capacity;
    }

    public function hasActiveSeason(Kingdom $kingdom): bool
    {
        $activeSeason = $this->seasonRepository->findOneBy([
            'kingdom' => $kingdom,
            'status' => LeagueSeasonStatus::Active,
        ]);

        return null !== $activeSeason;
    }
}
