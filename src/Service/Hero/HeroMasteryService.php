<?php

declare(strict_types=1);

namespace App\Service\Hero;

use App\Entity\Hero\Hero;
use App\Entity\Hero\SchoolMastery;
use App\Entity\Hero\WeaponMastery;
use App\Enum\ItemSubType;
use App\Enum\School;
use App\Repository\Hero\SchoolMasteryRepository;
use App\Repository\Hero\WeaponMasteryRepository;
use App\Repository\Item\ItemRepository;
use Doctrine\ORM\EntityManagerInterface;

class HeroMasteryService
{
    public const MASTERY_LEVEL_XP = [
        1 => 0,
        2 => 100,
        3 => 300,
        4 => 600,
        5 => 1000,
    ];

    public function __construct(
        private readonly ItemRepository $itemRepository,
        private readonly WeaponMasteryRepository $weaponMasteryRepository,
        private readonly SchoolMasteryRepository $schoolMasteryRepository,
        private readonly HeroChronicleService $heroChronicleService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Get all weapon/gear sub-types currently equipped by the hero.
     *
     * @return list<ItemSubType>
     */
    public function getEquippedSubTypes(Hero $hero): array
    {
        $items = $this->itemRepository->findBy(['equippedHero' => $hero]);
        $subTypes = [];

        foreach ($items as $item) {
            if (null !== $item->getSubType()) {
                $subTypes[$item->getSubType()->value] = $item->getSubType();
            }
        }

        return array_values($subTypes);
    }

    /**
     * Get magic schools of currently equipped spells.
     *
     * @return list<School>
     */
    public function getEquippedSpellSchools(Hero $hero): array
    {
        $schools = [];
        foreach ($hero->getHeroSpells() as $hs) {
            if ($hs->isEquipped()) {
                $schools[$hs->getSpell()->getSchool()->value] = $hs->getSpell()->getSchool();
            }
        }

        return array_values($schools);
    }

    /**
     * Handle match participation for a hero.
     * Increases attunement progress, and awards XP for equipped weapon types and magic schools.
     */
    public function processMatchParticipation(Hero $hero): void
    {
        $equippedSubTypes = $this->getEquippedSubTypes($hero);

        // Weapon mastery & attunement
        foreach ($equippedSubTypes as $subType) {
            $mastery = $this->getOrCreateWeaponMastery($hero, $subType);
            $mastery->setAttunementProgress(min(100, $mastery->getAttunementProgress() + 50));
            $this->addWeaponMasteryXp($hero, $subType, 15);
        }

        // Magic mastery
        $equippedSchools = $this->getEquippedSpellSchools($hero);
        foreach ($equippedSchools as $school) {
            $this->addSchoolMasteryXp($hero, $school, 15);
        }

        $this->em->flush();
    }

    /**
     * Daily reset decay tick for a hero.
     * Decreases attunement progress and XP of inactive masteries.
     */
    public function processDailyDecayTick(Hero $hero): void
    {
        $equippedSubTypes = $this->getEquippedSubTypes($hero);
        $equippedSchools = $this->getEquippedSpellSchools($hero);

        // 1. Decay inactive Weapon Masteries
        foreach ($hero->getWeaponMasteries() as $wm) {
            if (!in_array($wm->getStyle(), $equippedSubTypes, true)) {
                // Decay attunement
                $wm->setAttunementProgress(max(0, $wm->getAttunementProgress() - 20));

                // Decay XP
                $newXp = max(0, $wm->getXp() - 10);
                $wm->setXp($newXp);
                $this->recalculateWeaponMasteryTier($wm);
            }
        }

        // 2. Decay inactive School Masteries
        foreach ($hero->getSchoolMasteries() as $sm) {
            if (!in_array($sm->getSchool(), $equippedSchools, true)) {
                // Decay XP
                $newXp = max(0, $sm->getXp() - 10);
                $sm->setXp($newXp);
                $this->recalculateSchoolMasteryTier($sm);
            }
        }

        $this->em->flush();
    }

    /**
     * Award XP to a weapon mastery style.
     */
    public function addWeaponMasteryXp(Hero $hero, ItemSubType $style, int $amount): void
    {
        $wm = $this->getOrCreateWeaponMastery($hero, $style);
        $newXp = min(1000, $wm->getXp() + $amount);
        $wm->setXp($newXp);
        $this->recalculateWeaponMasteryTier($wm);
    }

    /**
     * Award XP to a magic school mastery.
     */
    public function addSchoolMasteryXp(Hero $hero, School $school, int $amount): void
    {
        $sm = $this->getOrCreateSchoolMastery($hero, $school);
        $newXp = min(1000, $sm->getXp() + $amount);
        $sm->setXp($newXp);
        $this->recalculateSchoolMasteryTier($sm);
    }

    /**
     * Get or create a WeaponMastery entity.
     */
    public function getOrCreateWeaponMastery(Hero $hero, ItemSubType $style): WeaponMastery
    {
        foreach ($hero->getWeaponMasteries() as $wm) {
            if ($wm->getStyle() === $style) {
                return $wm;
            }
        }

        $wm = $this->weaponMasteryRepository->findOneBy(['hero' => $hero, 'style' => $style]);
        if (null !== $wm) {
            return $wm;
        }

        $wm = new WeaponMastery();
        $wm->setHero($hero);
        $wm->setStyle($style);
        $wm->setMasteryTier(1);
        $wm->setXp(0);
        $wm->setAttunementProgress(0);

        $this->em->persist($wm);
        $hero->getWeaponMasteries()->add($wm);

        return $wm;
    }

    /**
     * Get or create a SchoolMastery entity.
     */
    public function getOrCreateSchoolMastery(Hero $hero, School $school): SchoolMastery
    {
        foreach ($hero->getSchoolMasteries() as $sm) {
            if ($sm->getSchool() === $school) {
                return $sm;
            }
        }

        $sm = $this->schoolMasteryRepository->findOneBy(['hero' => $hero, 'school' => $school]);
        if (null !== $sm) {
            return $sm;
        }

        $sm = new SchoolMastery();
        $sm->setHero($hero);
        $sm->setSchool($school);
        $sm->setMasteryTier(1);
        $sm->setXp(0);

        $this->em->persist($sm);
        $hero->getSchoolMasteries()->add($sm);

        return $sm;
    }

    private function recalculateWeaponMasteryTier(WeaponMastery $wm): void
    {
        $currentTier = $wm->getMasteryTier();
        $newTier = 1;
        $xp = $wm->getXp();
        foreach (self::MASTERY_LEVEL_XP as $tier => $reqXp) {
            if ($xp >= $reqXp) {
                $newTier = $tier;
            }
        }
        $wm->setMasteryTier($newTier);
        if ($newTier > $currentTier) {
            $this->heroChronicleService->recordMasteryGained($wm->getHero(), $wm->getStyle()->value, $newTier);
        }
    }

    private function recalculateSchoolMasteryTier(SchoolMastery $sm): void
    {
        $currentTier = $sm->getMasteryTier();
        $newTier = 1;
        $xp = $sm->getXp();
        foreach (self::MASTERY_LEVEL_XP as $tier => $reqXp) {
            if ($xp >= $reqXp) {
                $newTier = $tier;
            }
        }
        $sm->setMasteryTier($newTier);
        if ($newTier > $currentTier) {
            $this->heroChronicleService->recordMasteryGained($sm->getHero(), $sm->getSchool()->value, $newTier);
        }
    }
}
