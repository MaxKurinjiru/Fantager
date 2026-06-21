<?php

declare(strict_types=1);

namespace App\Service\Team;

use App\Entity\Hero\Hero;
use App\Entity\Team\Team;
use App\Enum\HeroStatus;
use App\Exception\UserFacingException;
use App\Repository\Hero\HeroRepository;

class TeamRosterService
{
    public const MIN_COMBAT_READY_HEROES = 6;

    public function __construct(
        private readonly HeroRepository $heroRepository,
    ) {
    }

    public function countCombatReadyHeroes(Team $team): int
    {
        return $this->heroRepository->countCombatReadyByTeam($team);
    }

    public function isCombatReady(Hero $hero): bool
    {
        return $hero->isCombatant() && HeroStatus::Available === $hero->getStatus();
    }

    public function canRemoveCombatReadyHero(Team $team, Hero $hero): bool
    {
        if (!$this->isCombatReady($hero)) {
            return false;
        }

        return $this->countCombatReadyHeroes($team) > self::MIN_COMBAT_READY_HEROES;
    }

    /**
     * @throws \DomainException
     */
    public function assertCanRemoveCombatReadyHero(Team $team, Hero $hero): void
    {
        if (!$this->isCombatReady($hero)) {
            throw new UserFacingException('error.hero_only_available_roster');
        }

        if ($this->countCombatReadyHeroes($team) <= self::MIN_COMBAT_READY_HEROES) {
            throw new UserFacingException('error.hero_roster_minimum', ['%min%' => self::MIN_COMBAT_READY_HEROES]);
        }
    }
}
