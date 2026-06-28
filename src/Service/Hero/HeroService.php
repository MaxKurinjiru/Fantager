<?php

declare(strict_types=1);

namespace App\Service\Hero;

use App\Entity\Hero\Hero;
use App\Entity\Team\Team;
use App\Exception\UserFacingException;
use App\Repository\Hero\HeroRepository;
use Doctrine\ORM\EntityManagerInterface;

class HeroService
{
    public function __construct(
        private readonly HeroRepository $heroRepository,
        private readonly EntityManagerInterface $em,
        private readonly \App\Service\Combat\CombatStatCalculator $combatStatCalculator,
        private readonly HeroMasteryService $heroMasteryService,
        private readonly HeroRatingCalculator $heroRatingCalculator,
    ) {
    }

    /** @return list<Hero> */
    public function listByTeam(Team $team): array
    {
        return $this->heroRepository->findBy(['team' => $team], ['id' => 'ASC']);
    }

    public function findForTeam(int $id, Team $team): ?Hero
    {
        return $this->heroRepository->findOneBy(['id' => $id, 'team' => $team]);
    }

    public function rename(Hero $hero, string $name): void
    {
        $name = trim($name);
        if ('' === $name) {
            throw new UserFacingException('error.hero_name_empty');
        }

        if (mb_strlen($name) > 100) {
            throw new UserFacingException('error.hero_name_too_long');
        }

        $hero->setName($name);
        $this->em->flush();
    }

    /** @return array<string, mixed> */
    public function serialize(Hero $hero): array
    {
        $combatStats = $this->combatStatCalculator->calculate($hero);
        $rating = $this->heroRatingCalculator->calculate($hero);

        return [
            'id' => $hero->getId(),
            'name' => $hero->getName(),
            'race' => $hero->getRace()->value,
            'level' => $hero->getLevel(),
            'xp' => $hero->getXp(),
            'age' => $hero->getAge(),
            'status' => $hero->getStatus()->value,
            'trait' => $hero->getTrait()?->value,
            'form' => $hero->getForm(),
            'fatigue' => $hero->getFatigue(),
            'morale' => $hero->getMorale(),
            'magic_capacity' => $hero->getMagicCapacity(),
            'weapon_masteries' => array_map(fn ($wm) => [
                'style' => $wm->getStyle()->value,
                'tier' => $wm->getMasteryTier(),
                'xp' => $wm->getXp(),
                'attunement_progress' => $wm->getAttunementProgress(),
            ], $hero->getWeaponMasteries()->toArray()),
            'school_masteries' => array_map(fn ($sm) => [
                'school' => $sm->getSchool()->value,
                'tier' => $sm->getMasteryTier(),
                'xp' => $sm->getXp(),
            ], $hero->getSchoolMasteries()->toArray()),
            'equipped_weapon_sub_types' => array_map(fn ($sub) => $sub->value, $this->heroMasteryService->getEquippedSubTypes($hero)),
            'attributes' => [
                'str' => $hero->getStr(),
                'dex' => $hero->getDex(),
                'kon' => $hero->getKon(),
                'spd' => $hero->getSpd(),
                'int' => $hero->getIntel(),
                'wil' => $hero->getWil(),
                'cha' => $hero->getCha(),
                'lck' => $hero->getLck(),
            ],
            'ratings' => [
                'base_ovr' => $rating->getBaseOvr(),
                'complex_rating' => $rating->getComplexRating(),
            ],
            'combat_stats' => [
                'max_hp' => $combatStats->getMaxHp(),
                'current_hp' => $combatStats->getCurrentHp(),
                'physical_attack' => $combatStats->getPhysicalAttack(),
                'spell_power' => $combatStats->getSpellPower(),
                'armor_value' => $combatStats->getArmorValue(),
                'physical_damage_reduction' => $combatStats->getPhysicalDamageReduction(),
                'magic_resistance' => $combatStats->getMagicResistance(),
                'magic_damage_reduction' => $combatStats->getMagicDamageReduction(),
                'base_initiative' => $combatStats->getBaseInitiative(),
                'accuracy_percent' => $combatStats->getAccuracyPercent(),
                'dodge_percent' => $combatStats->getDodgePercent(),
                'crit_percent' => $combatStats->getCritPercent(),
                // Trait-derived modifiers (metadata pro combat engine a UI)
                'crit_damage_multiplier' => $combatStats->getCritDamageMultiplier(),
                'morale_decay_multiplier' => $combatStats->getMoraleDecayMultiplier(),
                'arena_revenue_bonus' => $combatStats->getArenaRevenueBonus(),
                'clutch_hp_threshold' => $combatStats->getClutchHpThreshold(),
                'glass_jaw_hp_threshold' => $combatStats->getGlassJawHpThreshold(),
                'is_consistent_damage' => $combatStats->isConsistentDamage(),
                'ignores_race_synergy' => $combatStats->ignoresRaceSynergy(),
            ],
        ];
    }
}
