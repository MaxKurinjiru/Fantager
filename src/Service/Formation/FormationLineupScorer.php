<?php

declare(strict_types=1);

namespace App\Service\Formation;

use App\Entity\Hero\Hero;
use App\Enum\FormationPosition;
use App\Enum\HeroTrait;

/**
 * Shared front/back placement scores for NPC tactics and player roster fill.
 */
class FormationLineupScorer
{
    /**
     * @return array{front: float, back: float}
     */
    public function scores(Hero $hero): array
    {
        $readiness = 1.0;
        if ($hero->getFatigue() > 50) {
            $readiness *= 0.5;
        }
        if ($hero->getForm() < 40) {
            $readiness *= 0.6;
        }

        $front = ($hero->getKon() + $hero->getStr()) * $readiness;
        $back = ($hero->getIntel() + $hero->getSpd() + $hero->getDex()) * $readiness;

        $trait = $hero->getTrait();
        if (null !== $trait) {
            if (\in_array($trait, [HeroTrait::Berserker, HeroTrait::Overconfident, HeroTrait::BattleHardened], true)) {
                $front *= 1.20;
            }
            if (\in_array($trait, [HeroTrait::Glasscannon, HeroTrait::Reckless], true)) {
                $back *= 1.20;
                $front *= 0.85;
            }
            if (HeroTrait::Clutch === $trait) {
                $front *= 1.10;
            }
            if (\in_array($trait, [HeroTrait::Fragile, HeroTrait::GlassJaw], true)) {
                $front *= 0.80;
            }
            if ($this->isPurelyNegativeTrait($trait)) {
                $front *= 0.10;
                $back *= 0.10;
            }
        }

        return ['front' => $front, 'back' => $back];
    }

    public function isFrontRow(FormationPosition $position): bool
    {
        return \in_array($position, [
            FormationPosition::Front1,
            FormationPosition::Front2,
            FormationPosition::Front3,
        ], true);
    }

    /**
     * @param list<Hero> $heroes
     *
     * @return list<Hero>
     */
    public function sortForRow(array $heroes, bool $front): array
    {
        usort($heroes, function (Hero $a, Hero $b) use ($front): int {
            $key = $front ? 'front' : 'back';
            $byScore = $this->scores($b)[$key] <=> $this->scores($a)[$key];
            if (0 !== $byScore) {
                return $byScore;
            }

            return ($a->getId() ?? 0) <=> ($b->getId() ?? 0);
        });

        return $heroes;
    }

    /**
     * Pick a 3-3 starting lineup from combatants.
     *
     * Takes the six heroes with the highest max(front, back) score, then puts
     * the three with the largest front-minus-back delta in front (rest in back).
     * Player hole-fill stays {@see fillEmptyHeroIds()} (front holes first).
     *
     * @param list<Hero> $heroes
     *
     * @return array{front: list<Hero>, back: list<Hero>}
     */
    public function pickStartingLineup(array $heroes): array
    {
        $candidates = [];
        foreach ($heroes as $hero) {
            $heroId = $hero->getId();
            if (null === $heroId || $hero->isTrainer()) {
                continue;
            }
            $scores = $this->scores($hero);
            $candidates[] = [
                'hero' => $hero,
                'id' => $heroId,
                'front' => $scores['front'],
                'back' => $scores['back'],
                'best' => max($scores['front'], $scores['back']),
                'delta' => $scores['front'] - $scores['back'],
            ];
        }

        usort($candidates, static function (array $a, array $b): int {
            $byBest = $b['best'] <=> $a['best'];

            return 0 !== $byBest ? $byBest : $a['id'] <=> $b['id'];
        });
        $selected = array_slice($candidates, 0, 6);

        usort($selected, static function (array $a, array $b): int {
            $byDelta = $b['delta'] <=> $a['delta'];

            return 0 !== $byDelta ? $byDelta : $a['id'] <=> $b['id'];
        });

        $frontCount = min(3, \count($selected));
        $frontRows = array_slice($selected, 0, $frontCount);
        $backRows = array_slice($selected, $frontCount);

        usort($frontRows, static function (array $a, array $b): int {
            $byScore = $b['front'] <=> $a['front'];

            return 0 !== $byScore ? $byScore : $a['id'] <=> $b['id'];
        });
        usort($backRows, static function (array $a, array $b): int {
            $byScore = $b['back'] <=> $a['back'];

            return 0 !== $byScore ? $byScore : $a['id'] <=> $b['id'];
        });

        return [
            'front' => array_map(static fn (array $row): Hero => $row['hero'], $frontRows),
            'back' => array_map(static fn (array $row): Hero => $row['hero'], $backRows),
        ];
    }

    /**
     * Fill empty positions from remaining candidates. Occupied ids stay put.
     * Front holes take the highest remaining front score, then back holes.
     *
     * @param array<string, int|null> $heroIdsByPosition
     * @param list<Hero>              $candidates
     *
     * @return array<string, int|null>
     */
    public function fillEmptyHeroIds(array $heroIdsByPosition, array $candidates): array
    {
        $result = [];
        $used = [];
        foreach (FormationPosition::cases() as $position) {
            $id = $heroIdsByPosition[$position->value] ?? null;
            $result[$position->value] = $id;
            if (null !== $id) {
                $used[$id] = true;
            }
        }

        $emptyFront = [];
        $emptyBack = [];
        foreach (FormationPosition::cases() as $position) {
            if (null !== $result[$position->value]) {
                continue;
            }
            if ($this->isFrontRow($position)) {
                $emptyFront[] = $position;
            } else {
                $emptyBack[] = $position;
            }
        }

        $remaining = [];
        foreach ($candidates as $hero) {
            $heroId = $hero->getId();
            if (null === $heroId || isset($used[$heroId])) {
                continue;
            }
            $remaining[] = $hero;
        }

        $this->placeHeroIds($result, $used, $remaining, $emptyFront, true);
        $this->placeHeroIds($result, $used, $remaining, $emptyBack, false);

        return $result;
    }

    /**
     * @param array<string, int|null> $result
     * @param array<int, true>        $used
     * @param list<Hero>              $remaining
     * @param list<FormationPosition> $positions
     */
    private function placeHeroIds(
        array &$result,
        array &$used,
        array &$remaining,
        array $positions,
        bool $front,
    ): void {
        $pool = [];
        foreach ($remaining as $candidate) {
            $candidateId = $candidate->getId();
            if (null === $candidateId || isset($used[$candidateId])) {
                continue;
            }
            $pool[] = $candidate;
        }
        $pool = $this->sortForRow($pool, $front);

        foreach ($positions as $index => $position) {
            $hero = $pool[$index] ?? null;
            if (!$hero instanceof Hero) {
                return;
            }
            $heroId = $hero->getId();
            if (null === $heroId) {
                return;
            }
            $used[$heroId] = true;
            $result[$position->value] = $heroId;
        }

        $remaining = array_values(array_filter(
            $remaining,
            static fn (Hero $hero): bool => null !== $hero->getId() && !isset($used[$hero->getId()]),
        ));
    }

    private function isPurelyNegativeTrait(HeroTrait $trait): bool
    {
        return \in_array($trait, [
            HeroTrait::Slacker,
            HeroTrait::Fragile,
            HeroTrait::GlassJaw,
        ], true);
    }
}
