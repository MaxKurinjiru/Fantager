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

    private function isPurelyNegativeTrait(HeroTrait $trait): bool
    {
        return \in_array($trait, [
            HeroTrait::Slacker,
            HeroTrait::Fragile,
            HeroTrait::GlassJaw,
        ], true);
    }
}
