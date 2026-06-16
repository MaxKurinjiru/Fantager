<?php

declare(strict_types=1);

namespace App\Service\Team;

use App\Entity\Hero\Hero;
use App\Entity\Team\Team;
use App\Enum\HeroStatus;
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
        return HeroStatus::Available === $hero->getStatus();
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
            throw new \DomainException('Only available heroes can leave the roster.');
        }

        if ($this->countCombatReadyHeroes($team) <= self::MIN_COMBAT_READY_HEROES) {
            throw new \DomainException(sprintf(
                'Cannot remove hero. Team must keep at least %d combat-ready heroes to play matches.',
                self::MIN_COMBAT_READY_HEROES
            ));
        }
    }
}
