<?php

declare(strict_types=1);

namespace App\Service\Team;

use App\Entity\Formation\Formation;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;
use App\Repository\Formation\FormationRepository;
use App\Repository\Headquarters\HeadquartersRepository;
use App\Repository\Hero\HeroRepository;
use App\Repository\Team\TeamRepository;
use App\ValueObject\Combat\MatchOutcome;
use Doctrine\ORM\EntityManagerInterface;

class TeamMoraleReputationService
{
    public const MIN_REPUTATION = 0;
    public const MAX_REPUTATION = 10000;
    public const MIN_MORALE = 0;
    public const MAX_MORALE = 100;
    public const BASELINE_MORALE = 50;

    // Reputation adjustments
    public const REP_WIN = 10;
    public const REP_LOSS = -5;
    public const REP_DRAW = 2;
    public const REP_FORFEIT_WIN = 10;
    public const REP_FORFEIT_LOSS = -10;

    // Team Morale adjustments
    public const TEAM_MORALE_WIN = 8;
    public const TEAM_MORALE_LOSS = -6;
    public const TEAM_MORALE_DRAW = 1;
    public const TEAM_MORALE_FORFEIT_WIN = 5;
    public const TEAM_MORALE_FORFEIT_LOSS = -15;
    public const TEAM_MORALE_DOUBLE_FORFEIT = -10;

    // Hero Morale adjustments (Active lineup)
    public const HERO_MORALE_WIN = 10;
    public const HERO_MORALE_LOSS = -8;
    public const HERO_MORALE_DRAW = 2;

    // Hero Morale adjustments (Reserves)
    public const HERO_MORALE_RESERVE_WIN = 4;
    public const HERO_MORALE_RESERVE_LOSS = -3;
    public const HERO_MORALE_RESERVE_DRAW = 1;

    // Forfeit Hero Morale adjustments (applied to all combatants)
    public const HERO_MORALE_FORFEIT_WIN = 5;
    public const HERO_MORALE_FORFEIT_LOSS = -15;
    public const HERO_MORALE_DOUBLE_FORFEIT = -10;

    public function __construct(
        private readonly TeamRepository $teamRepository,
        private readonly HeroRepository $heroRepository,
        private readonly FormationRepository $formationRepository,
        private readonly HeadquartersRepository $hqRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function applyMatchResult(
        Team $homeTeam,
        Team $awayTeam,
        MatchOutcome $outcome,
        ?Formation $homeFormation,
        ?Formation $awayFormation,
    ): void {
        $homeScore = $outcome->getHomeScore();
        $awayScore = $outcome->getAwayScore();
        $isForfeit = $outcome->isForfeit();

        // 1. Determine match outcome from Home team's perspective
        if ($homeScore > $awayScore) {
            $homeResult = 'win';
            $awayResult = 'loss';
        } elseif ($homeScore < $awayScore) {
            $homeResult = 'loss';
            $awayResult = 'win';
        } else {
            $homeResult = 'draw';
            $awayResult = 'draw';
        }

        // 2. Process both teams
        $this->updateTeamStats($homeTeam, $homeResult, $isForfeit, $homeFormation);
        $this->updateTeamStats($awayTeam, $awayResult, $isForfeit, $awayFormation);

        $this->em->flush();
    }

    private function updateTeamStats(
        Team $team,
        string $result,
        bool $isForfeit,
        ?Formation $formation,
    ): void {
        // Find all combatants for this team
        $allCombatants = $this->heroRepository->findCombatantsByTeam($team);
        if (empty($allCombatants)) {
            return;
        }

        // Identify active vs reserve heroes
        $activeHeroes = [];
        $reserves = [];

        // Fallback to default formation if no formation is assigned for the fixture and it's not a forfeit
        if (null === $formation && !$isForfeit) {
            $formation = $this->formationRepository->findDefaultForTeam($team);
        }

        if (null !== $formation) {
            foreach ($formation->getSlots() as $slot) {
                $hero = $slot->getHero();
                if (null !== $hero && $hero->getTeam()->getId() === $team->getId()) {
                    $heroId = $hero->getId();
                    if (null !== $heroId) {
                        $activeHeroes[$heroId] = $hero;
                    }
                }
            }
        }

        foreach ($allCombatants as $combatant) {
            $combatantId = $combatant->getId();
            if (null === $combatantId || !isset($activeHeroes[$combatantId])) {
                $reserves[] = $combatant;
            }
        }

        // Calculate average charisma of the lineup that played (or all combatants if no lineup played)
        $heroesToAverage = !empty($activeHeroes) ? array_values($activeHeroes) : $allCombatants;
        $totalCha = 0;
        foreach ($heroesToAverage as $h) {
            $totalCha += $h->getCha();
        }
        $avgCha = $totalCha / count($heroesToAverage);

        // Charisma buffering factor: reduces losses, increases gains
        $charismaFactor = min(0.5, max(0.0, ($avgCha - 10.0) * 0.02));
        $lossMultiplier = 1.0 - $charismaFactor;
        $gainMultiplier = 1.0 + $charismaFactor;

        // 1. Calculate Reputation Delta
        $repDelta = 0;
        if ($isForfeit) {
            if ('win' === $result) {
                $repDelta = self::REP_FORFEIT_WIN;
            } elseif ('loss' === $result) {
                $repDelta = self::REP_FORFEIT_LOSS;
            }
        // Double forfeit (draw) -> 0 reputation change
        } else {
            if ('win' === $result) {
                $repDelta = self::REP_WIN;
            } elseif ('loss' === $result) {
                $repDelta = self::REP_LOSS;
            } else {
                $repDelta = self::REP_DRAW;
            }
        }

        $newRep = max(self::MIN_REPUTATION, min(self::MAX_REPUTATION, $team->getReputation() + $repDelta));
        $team->setReputation($newRep);

        // 2. Calculate Team Morale Delta
        $teamMoraleDelta = 0;
        if ($isForfeit) {
            if ('win' === $result) {
                $teamMoraleDelta = self::TEAM_MORALE_FORFEIT_WIN;
            } elseif ('loss' === $result) {
                $teamMoraleDelta = self::TEAM_MORALE_FORFEIT_LOSS;
            } else {
                $teamMoraleDelta = self::TEAM_MORALE_DOUBLE_FORFEIT;
            }
        } else {
            if ('win' === $result) {
                $teamMoraleDelta = self::TEAM_MORALE_WIN;
            } elseif ('loss' === $result) {
                $teamMoraleDelta = self::TEAM_MORALE_LOSS;
            } else {
                $teamMoraleDelta = self::TEAM_MORALE_DRAW;
            }
        }

        // Apply charisma buffering to team morale delta
        if ($teamMoraleDelta > 0) {
            $teamMoraleDelta = (int) round($teamMoraleDelta * $gainMultiplier);
        } else {
            $teamMoraleDelta = (int) round($teamMoraleDelta * $lossMultiplier);
        }

        $newTeamMorale = max(self::MIN_MORALE, min(self::MAX_MORALE, $team->getMorale() + $teamMoraleDelta));
        $team->setMorale($newTeamMorale);

        // 3. Calculate Hero Morale Deltas
        if ($isForfeit) {
            // Under forfeit, all combatants get the same change
            $heroMoraleDelta = 0;
            if ('win' === $result) {
                $heroMoraleDelta = self::HERO_MORALE_FORFEIT_WIN;
            } elseif ('loss' === $result) {
                $heroMoraleDelta = self::HERO_MORALE_FORFEIT_LOSS;
            } else {
                $heroMoraleDelta = self::HERO_MORALE_DOUBLE_FORFEIT;
            }

            if ($heroMoraleDelta > 0) {
                $heroMoraleDelta = (int) round($heroMoraleDelta * $gainMultiplier);
            } else {
                $heroMoraleDelta = (int) round($heroMoraleDelta * $lossMultiplier);
            }

            foreach ($allCombatants as $combatant) {
                $newMorale = max(self::MIN_MORALE, min(self::MAX_MORALE, $combatant->getMorale() + $heroMoraleDelta));
                $combatant->setMorale($newMorale);
            }
        } else {
            // Update active heroes
            $activeDelta = 0;
            if ('win' === $result) {
                $activeDelta = self::HERO_MORALE_WIN;
            } elseif ('loss' === $result) {
                $activeDelta = self::HERO_MORALE_LOSS;
            } else {
                $activeDelta = self::HERO_MORALE_DRAW;
            }

            if ($activeDelta > 0) {
                $activeDelta = (int) round($activeDelta * $gainMultiplier);
            } else {
                $activeDelta = (int) round($activeDelta * $lossMultiplier);
            }

            foreach ($activeHeroes as $hero) {
                $newMorale = max(self::MIN_MORALE, min(self::MAX_MORALE, $hero->getMorale() + $activeDelta));
                $hero->setMorale($newMorale);
            }

            // Update reserve heroes
            $reserveDelta = 0;
            if ('win' === $result) {
                $reserveDelta = self::HERO_MORALE_RESERVE_WIN;
            } elseif ('loss' === $result) {
                $reserveDelta = self::HERO_MORALE_RESERVE_LOSS;
            } else {
                $reserveDelta = self::HERO_MORALE_RESERVE_DRAW;
            }

            if ($reserveDelta > 0) {
                $reserveDelta = (int) round($reserveDelta * $gainMultiplier);
            } else {
                $reserveDelta = (int) round($reserveDelta * $lossMultiplier);
            }

            foreach ($reserves as $hero) {
                $newMorale = max(self::MIN_MORALE, min(self::MAX_MORALE, $hero->getMorale() + $reserveDelta));
                $hero->setMorale($newMorale);
            }
        }
    }

    public function processDailyEvolutionTick(Kingdom $kingdom, ?Team $team = null): int
    {
        if (null !== $team) {
            $teams = [$team];
        } else {
            $teams = $this->teamRepository->findBy(['kingdom' => $kingdom]);
        }
        $updated = 0;

        foreach ($teams as $team) {
            // Find barracks facility level
            $barracksLevel = 1;
            $hq = $this->hqRepository->findOneBy(['team' => $team]);
            if (null !== $hq) {
                foreach ($hq->getFacilities() as $facility) {
                    if (\App\Enum\FacilityType::Barracks === $facility->getType()) {
                        $barracksLevel = $facility->getLevel();
                        break;
                    }
                }
            }

            // 1. Evolve Team Morale
            $teamGap = $team->getMorale() - self::BASELINE_MORALE;
            if (0 !== $teamGap) {
                $teamDelta = 0;
                if ($teamGap > 0) {
                    // Decay towards 50, slowed by Barracks
                    $decayMultiplier = 1.0 / (1.0 + 0.05 * $barracksLevel);
                    $teamDelta = (int) round(-$teamGap * 0.05 * $decayMultiplier);
                    if (0 === $teamDelta) {
                        $teamDelta = -1;
                    }
                } else {
                    // Recovery towards 50, enhanced by Barracks
                    $recoveryMultiplier = 1.0 + 0.10 * $barracksLevel;
                    $teamDelta = (int) round(-$teamGap * 0.05 * $recoveryMultiplier);
                    if (0 === $teamDelta) {
                        $teamDelta = 1;
                    }
                }
                $team->setMorale(max(self::MIN_MORALE, min(self::MAX_MORALE, $team->getMorale() + $teamDelta)));
            }

            // 2. Evolve Hero Morale for all combatants
            $heroes = $this->heroRepository->findCombatantsByTeam($team);
            foreach ($heroes as $hero) {
                $heroGap = $hero->getMorale() - self::BASELINE_MORALE;
                if (0 !== $heroGap) {
                    $heroDelta = 0;
                    if ($heroGap > 0) {
                        // Decay towards 50, slowed by Barracks
                        $decayMultiplier = 1.0 / (1.0 + 0.05 * $barracksLevel);
                        $heroDelta = (int) round(-$heroGap * 0.05 * $decayMultiplier);
                        if (0 === $heroDelta) {
                            $heroDelta = -1;
                        }
                    } else {
                        // Recovery towards 50, enhanced by Barracks
                        $recoveryMultiplier = 1.0 + 0.10 * $barracksLevel;
                        $heroDelta = (int) round(-$heroGap * 0.05 * $recoveryMultiplier);
                        if (0 === $heroDelta) {
                            $heroDelta = 1;
                        }
                    }
                    $hero->setMorale(max(self::MIN_MORALE, min(self::MAX_MORALE, $hero->getMorale() + $heroDelta)));
                }
            }

            ++$updated;
        }

        $this->em->flush();

        return $updated;
    }
}
