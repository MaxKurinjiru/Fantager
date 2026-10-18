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

    /**
     * Base XP awarded per match at full attunement (100%).
     * Scales linearly: xp_gain = round(BASE_MATCH_XP * attunement / 100).
     */
    public const BASE_MATCH_XP = 30;

    /**
     * Maximum XP lost per day at zero attunement (0%).
     * Scales linearly: xp_decay = round(BASE_DECAY_XP * (1 - attunement / 100))
     * At full attunement (100%) no XP is lost.
     */
    public const BASE_DECAY_XP = 20;

    public const ATTUNEMENT_DECAY = 10;

    public const SCHOOL_DECAY_XP = 10;

    /** No decay while the style or school was exercised inside this many days. */
    public const DECAY_GRACE_DAYS = 7;

    /** XP cannot fall through the current tier floor inside this many days since last exercise. */
    public const TIER_PROTECTION_DAYS = 14;

    /** Daily XP loss is also capped at this fraction of the last XP gain, when that gain is known. */
    public const DECAY_GAIN_FRACTION = 0.25;

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
     * Weapon mastery XP gain scales with attunement: fully attuned = BASE_MATCH_XP, zero = 0 XP.
     */
    public function processMatchParticipation(Hero $hero, ?\DateTimeImmutable $now = null): void
    {
        $now = $now ?? new \DateTimeImmutable();
        $equippedSubTypes = $this->getEquippedSubTypes($hero);

        // Weapon mastery & attunement. Attunement stays match-only.
        foreach ($equippedSubTypes as $subType) {
            $mastery = $this->getOrCreateWeaponMastery($hero, $subType);
            $mastery->setAttunementProgress(min(100, $mastery->getAttunementProgress() + 50));
            $xpGain = (int) round(self::BASE_MATCH_XP * $mastery->getAttunementProgress() / 100);
            if ($xpGain > 0) {
                $this->addWeaponMasteryXp($hero, $subType, $xpGain);
                $mastery->setLastGainXp($xpGain);
            }
            $mastery->setLastExercisedAt($now);
        }

        // Magic mastery — fixed XP, no attunement mechanic
        $equippedSchools = $this->getEquippedSpellSchools($hero);
        foreach ($equippedSchools as $school) {
            $this->recordSchoolExercise($hero, $school, 15, $now);
        }

        $this->em->flush();
    }

    /**
     * Weekly magic training grants a small amount of school XP and counts as exercise for decay.
     */
    public function recordSchoolExercise(Hero $hero, School $school, int $xpGain, \DateTimeImmutable $now): void
    {
        if ($xpGain > 0) {
            $this->addSchoolMasteryXp($hero, $school, $xpGain);
        } else {
            $this->getOrCreateSchoolMastery($hero, $school);
        }

        foreach ($hero->getSchoolMasteries() as $mastery) {
            if ($mastery->getSchool() === $school) {
                $mastery->setLastExercisedAt($now);
                if ($xpGain > 0) {
                    $mastery->setLastGainXp($xpGain);
                }

                return;
            }
        }
    }

    /**
     * Daily reset decay tick for a hero.
     * Weapon mastery XP decay scales inversely with attunement: zero attunement = BASE_DECAY_XP lost,
     * full attunement = 0 XP lost. Attunement itself decays at a fixed rate.
     * School mastery uses fixed decay (no attunement mechanic).
     */
    public function processDailyDecayTick(Hero $hero, ?\DateTimeImmutable $now = null): void
    {
        $now = $now ?? new \DateTimeImmutable();

        foreach ($hero->getWeaponMasteries() as $wm) {
            $this->decayWeaponMastery($wm, $now);
        }

        foreach ($hero->getSchoolMasteries() as $sm) {
            $this->decaySchoolMastery($sm, $now);
        }

        $this->em->flush();
    }

    private function decayWeaponMastery(WeaponMastery $mastery, \DateTimeImmutable $now): void
    {
        if ($this->skipDecayBecauseRecent($mastery->getLastExercisedAt(), $now)) {
            if (null === $mastery->getLastExercisedAt()) {
                $mastery->setLastExercisedAt($now);
            }

            return;
        }

        $mastery->setAttunementProgress(max(0, $mastery->getAttunementProgress() - self::ATTUNEMENT_DECAY));

        $xpDecay = (int) round(self::BASE_DECAY_XP * (1 - $mastery->getAttunementProgress() / 100));
        $xpDecay = $this->capDecayToLastGain($xpDecay, $mastery->getLastGainXp());
        if ($xpDecay <= 0) {
            return;
        }

        $mastery->setXp($this->applyXpDecay($mastery->getXp(), $mastery->getMasteryTier(), $xpDecay, $mastery->getLastExercisedAt(), $now));
        $this->recalculateWeaponMasteryTier($mastery);
    }

    private function decaySchoolMastery(SchoolMastery $mastery, \DateTimeImmutable $now): void
    {
        if ($this->skipDecayBecauseRecent($mastery->getLastExercisedAt(), $now)) {
            if (null === $mastery->getLastExercisedAt()) {
                $mastery->setLastExercisedAt($now);
            }

            return;
        }

        $xpDecay = $this->capDecayToLastGain(self::SCHOOL_DECAY_XP, $mastery->getLastGainXp());
        if ($xpDecay <= 0) {
            return;
        }

        $mastery->setXp($this->applyXpDecay($mastery->getXp(), $mastery->getMasteryTier(), $xpDecay, $mastery->getLastExercisedAt(), $now));
        $this->recalculateSchoolMasteryTier($mastery);
    }

    /**
     * A null timestamp starts the clock without decaying, so existing rows are not wiped on the first tick after deploy.
     */
    private function skipDecayBecauseRecent(?\DateTimeImmutable $lastExercisedAt, \DateTimeImmutable $now): bool
    {
        if (null === $lastExercisedAt) {
            return true;
        }

        return $this->exercisedWithinDays($lastExercisedAt, $now, self::DECAY_GRACE_DAYS);
    }

    private function capDecayToLastGain(int $xpDecay, int $lastGainXp): int
    {
        if ($lastGainXp <= 0) {
            return $xpDecay;
        }

        return min($xpDecay, (int) round($lastGainXp * self::DECAY_GAIN_FRACTION));
    }

    private function applyXpDecay(int $xp, int $tier, int $xpDecay, ?\DateTimeImmutable $lastExercisedAt, \DateTimeImmutable $now): int
    {
        $newXp = max(0, $xp - $xpDecay);
        if (null !== $lastExercisedAt && $this->exercisedWithinDays($lastExercisedAt, $now, self::TIER_PROTECTION_DAYS)) {
            $floor = self::MASTERY_LEVEL_XP[$tier] ?? 0;
            $newXp = max($newXp, min($xp, $floor));
        }

        return $newXp;
    }

    private function exercisedWithinDays(\DateTimeImmutable $lastExercisedAt, \DateTimeImmutable $now, int $days): bool
    {
        return ($now->getTimestamp() - $lastExercisedAt->getTimestamp()) < ($days * 86400);
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
