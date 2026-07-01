<?php

declare(strict_types=1);

namespace App\Service\Team;

use App\Entity\Team\Team;
use App\Enum\HeroTrait;

trait NpcSimulationHelperTrait
{
    private function getHelperEconomicRole(Team $team): string
    {
        $id = $team->getId() ?? 0;

        $roles = [
            NpcSimulationService::ROLE_MERCENARY_ACADEMY,
            NpcSimulationService::ROLE_VETERAN_GUILD,
            NpcSimulationService::ROLE_ROYAL_COLLECTOR,
            NpcSimulationService::ROLE_SCAVENGER_CLAN,
        ];

        return $roles[$id % \count($roles)];
    }

    private function isPurelyNegativeTrait(?HeroTrait $trait): bool
    {
        if (null === $trait) {
            return false;
        }

        return \in_array($trait, [
            HeroTrait::Volatile,
            HeroTrait::Slacker,
            HeroTrait::Fragile,
            HeroTrait::GlassJaw,
        ], true);
    }
}
