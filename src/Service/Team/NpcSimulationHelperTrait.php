<?php

declare(strict_types=1);

namespace App\Service\Team;

use App\Entity\Hero\Hero;
use App\Entity\Team\Team;
use App\Enum\HeroStatus;
use App\Enum\HeroTrait;

trait NpcSimulationHelperTrait
{
    /** Buy a trainer as an upgrade when they beat current coverage of a focus stat by this ratio. */
    private const NPC_TRAINER_UPGRADE_RATIO = 1.10;

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

    /**
     * How many trainers the NPC wants to keep. Capped by HQ. Veteran/collector leave
     * a buy slot after the first internal promotion so the trainer market stays alive.
     */
    private function getNpcTrainerTarget(string $role, int $hqTrainerLimit): int
    {
        $desired = match ($role) {
            NpcSimulationService::ROLE_VETERAN_GUILD,
            NpcSimulationService::ROLE_ROYAL_COLLECTOR => 2,
            default => 1,
        };

        return max(1, min($hqTrainerLimit, $desired));
    }

    /**
     * Attributes this NPC actually trains (role template, plus lineup weapon attr).
     * Magic training uses INT/WIL; Form is not a trainer-quality stat.
     *
     * @return list<string>
     */
    private function npcTrainerFocusAttributes(string $role, ?string $lineupWeaponAttr = null): array
    {
        $attrs = match ($role) {
            NpcSimulationService::ROLE_MERCENARY_ACADEMY => ['str', 'spd'],
            NpcSimulationService::ROLE_VETERAN_GUILD => ['kon', 'wil'],
            NpcSimulationService::ROLE_ROYAL_COLLECTOR => ['int', 'wil'],
            NpcSimulationService::ROLE_SCAVENGER_CLAN => ['spd', 'lck'],
            default => ['str', 'spd'],
        };
        if (null !== $lineupWeaponAttr && '' !== $lineupWeaponAttr) {
            array_unshift($attrs, $lineupWeaponAttr);
        }

        return array_values(array_unique($attrs));
    }

    private function npcTrainerFocusStat(Hero $hero, string $attr): int
    {
        try {
            return match ($attr) {
                'str' => $hero->getStrRaw(),
                'dex' => $hero->getDexRaw(),
                'kon' => $hero->getKonRaw(),
                'spd' => $hero->getSpdRaw(),
                'int' => $hero->getIntelRaw(),
                'wil' => $hero->getWilRaw(),
                'cha' => $hero->getChaRaw(),
                'lck' => $hero->getLckRaw(),
                default => 0,
            };
        } catch (\Error) {
            return 0;
        }
    }

    /**
     * @param list<string> $focuses
     */
    private function npcTrainerValue(Hero $hero, array $focuses): int
    {
        $best = 0;
        foreach ($focuses as $attr) {
            $best = max($best, $this->npcTrainerFocusStat($hero, $attr));
        }

        return $best;
    }

    /**
     * Keep the specialist for each training focus, then fill remaining keep slots by best focus stat.
     *
     * @param list<Hero>   $trainers
     * @param list<string> $focuses
     *
     * @return array<int, true>
     */
    private function npcProtectedTrainerIds(array $trainers, int $keep, array $focuses): array
    {
        $picked = [];
        if ($keep <= 0 || [] === $trainers) {
            return $picked;
        }

        foreach ($focuses as $attr) {
            if (\count($picked) >= $keep) {
                break;
            }
            $best = null;
            $bestVal = 0;
            foreach ($trainers as $trainer) {
                $id = $trainer->getId();
                if (null === $id || isset($picked[$id])) {
                    continue;
                }
                $value = $this->npcTrainerFocusStat($trainer, $attr);
                if ($value > $bestVal) {
                    $bestVal = $value;
                    $best = $trainer;
                }
            }
            $pickedId = $best?->getId();
            if (null !== $pickedId && $bestVal > 0) {
                $picked[$pickedId] = true;
            }
        }

        if (\count($picked) < $keep) {
            $sorted = $trainers;
            usort($sorted, function (Hero $a, Hero $b) use ($focuses): int {
                $cmp = $this->npcTrainerValue($b, $focuses) <=> $this->npcTrainerValue($a, $focuses);
                if (0 !== $cmp) {
                    return $cmp;
                }
                $cmp = $b->getLevel() <=> $a->getLevel();
                if (0 !== $cmp) {
                    return $cmp;
                }

                return ($a->getId() ?? 0) <=> ($b->getId() ?? 0);
            });
            foreach ($sorted as $trainer) {
                if (\count($picked) >= $keep) {
                    break;
                }
                $id = $trainer->getId();
                if (null !== $id && !isset($picked[$id])) {
                    $picked[$id] = true;
                }
            }
        }

        return $picked;
    }

    /**
     * @param list<Hero>   $ownTrainers
     * @param list<string> $focuses
     */
    private function npcTrainerListingIsUseful(Hero $listing, array $ownTrainers, array $focuses): bool
    {
        if ([] === $ownTrainers || [] === $focuses) {
            return true;
        }

        $anyCoverage = false;
        foreach ($focuses as $attr) {
            $current = 0;
            foreach ($ownTrainers as $trainer) {
                $current = max($current, $this->npcTrainerFocusStat($trainer, $attr));
            }
            $listingStat = $this->npcTrainerFocusStat($listing, $attr);
            if ($current > 0) {
                $anyCoverage = true;
            }
            if ($current <= 0 && $listingStat > 0) {
                return true;
            }
            if ($this->npcValueMeets($listingStat, $current, self::NPC_TRAINER_UPGRADE_RATIO)) {
                return true;
            }
        }

        return !$anyCoverage;
    }

    /**
     * @param list<Hero> $heroes
     */
    private function countLiveTrainers(array $heroes): int
    {
        $count = 0;
        foreach ($heroes as $hero) {
            if ($hero->isTrainer() && !\in_array($hero->getStatus(), [HeroStatus::Dead, HeroStatus::Retired], true)) {
                ++$count;
            }
        }

        return $count;
    }

    private function npcValue(Hero $hero): int
    {
        return $hero->getComplexRating();
    }

    /**
     * @param list<Hero> $heroes
     *
     * @return list<Hero>
     */
    private function sortHeroesByNpcValueDesc(array $heroes): array
    {
        usort($heroes, function (Hero $a, Hero $b): int {
            $cmp = $this->npcValue($b) <=> $this->npcValue($a);
            if (0 !== $cmp) {
                return $cmp;
            }
            $cmp = $b->getLevel() <=> $a->getLevel();
            if (0 !== $cmp) {
                return $cmp;
            }

            return ($a->getId() ?? 0) <=> ($b->getId() ?? 0);
        });

        return $heroes;
    }

    /**
     * @param list<Hero> $heroes
     *
     * @return array<int, true>
     */
    private function npcProtectedIds(array $heroes, int $keep): array
    {
        $ids = [];
        foreach (array_slice($this->sortHeroesByNpcValueDesc($heroes), 0, max(0, $keep)) as $hero) {
            $id = $hero->getId();
            if (null !== $id) {
                $ids[$id] = true;
            }
        }

        return $ids;
    }

    /**
     * @param list<Hero> $heroes
     */
    private function medianNpcValue(array $heroes): int
    {
        if ([] === $heroes) {
            return 0;
        }
        $values = array_map(fn (Hero $hero): int => $this->npcValue($hero), $heroes);
        sort($values);
        $n = \count($values);
        $mid = intdiv($n, 2);
        if (0 !== $n % 2) {
            return $values[$mid];
        }

        return (int) round(($values[$mid - 1] + $values[$mid]) / 2);
    }

    /**
     * When baseline is 0, a ratio above 1 requires a strictly positive candidate;
     * a ratio of 1 or below accepts any non-negative candidate (empty-roster fill).
     */
    private function npcValueMeets(int $candidate, int $baseline, float $ratio): bool
    {
        if ($baseline <= 0) {
            return $ratio <= 1.0 ? $candidate >= 0 : $candidate > 0;
        }

        return $candidate >= (int) ceil($baseline * $ratio);
    }

    /**
     * @param list<Hero> $heroes
     */
    private function minNpcValue(array $heroes): int
    {
        if ([] === $heroes) {
            return 0;
        }
        $min = null;
        foreach ($heroes as $hero) {
            $value = $this->npcValue($hero);
            if (null === $min || $value < $min) {
                $min = $value;
            }
        }

        return (int) $min;
    }

    /**
     * @param list<Hero> $heroes
     */
    private function countLiveCombatants(array $heroes): int
    {
        $count = 0;
        foreach ($heroes as $hero) {
            if ($hero->isCombatant() && !\in_array($hero->getStatus(), [HeroStatus::Dead, HeroStatus::Retired], true)) {
                ++$count;
            }
        }

        return $count;
    }

    private function isPurelyNegativeTrait(?HeroTrait $trait): bool
    {
        if (null === $trait) {
            return false;
        }

        return \in_array($trait, [
            HeroTrait::Slacker,
            HeroTrait::Fragile,
            HeroTrait::GlassJaw,
        ], true);
    }
}
