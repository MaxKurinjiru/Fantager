<?php

declare(strict_types=1);

namespace App\Service\Team;

use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;

/**
 * Handles autonomous behaviors for NPC teams including tactics, training, and economy.
 * This class acts as a facade delegating work to specialized simulators.
 */
class NpcSimulationService
{
    use NpcSimulationHelperTrait;

    public const ROLE_MERCENARY_ACADEMY = 'mercenary_academy'; // Produces combat heroes
    public const ROLE_VETERAN_GUILD = 'veteran_guild';         // Produces trainers
    public const ROLE_ROYAL_COLLECTOR = 'royal_collector';     // Cash buyer (gold faucet)
    public const ROLE_SCAVENGER_CLAN = 'scavenger_clan';       // Low-end liquidity / dismantler

    public function __construct(
        private readonly NpcTacticsSimulator $tacticsSimulator,
        private readonly NpcTrainingSimulator $trainingSimulator,
        private readonly NpcEconomySimulator $economySimulator,
    ) {
    }

    /**
     * Determine the economic role/archetype of a team deterministically.
     */
    public function getEconomicRole(Team $team): string
    {
        return $this->getHelperEconomicRole($team);
    }

    /**
     * Run tactical simulation: optimize formation slotting (3-3), rotate tired heroes, and auto-equip gear.
     */
    public function simulateTactics(Kingdom $kingdom, \DateTimeImmutable $now, ?Team $team = null): void
    {
        $this->tacticsSimulator->simulateTactics($kingdom, $now, $team);
    }

    /**
     * Run training simulation: assign trainers and trainees before the weekly training tick.
     */
    public function simulateTraining(Kingdom $kingdom, \DateTimeImmutable $now, ?Team $team = null): void
    {
        $this->trainingSimulator->simulateTraining($kingdom, $now, $team);
    }

    /**
     * Run daily management and economy simulation: proactive dismissal, roster recycling, and summoning.
     */
    public function simulateDailyManagementAndEconomy(Kingdom $kingdom, \DateTimeImmutable $now, ?Team $team = null): void
    {
        $this->economySimulator->simulateDailyManagementAndEconomy($kingdom, $now, $team);
    }

    /**
     * Run marketplace simulation: buying and selling items/heroes twice weekly.
     */
    public function simulateMarketplaceActions(Kingdom $kingdom, \DateTimeImmutable $now, ?Team $team = null): void
    {
        $this->economySimulator->simulateMarketplaceActions($kingdom, $now, $team);
    }

    /**
     * Run weekly management and economy simulation: HQ upgrades.
     */
    public function simulateWeeklyManagementAndEconomy(Kingdom $kingdom, \DateTimeImmutable $now, ?Team $team = null): void
    {
        $this->economySimulator->simulateWeeklyManagementAndEconomy($kingdom, $now, $team);
    }
}
