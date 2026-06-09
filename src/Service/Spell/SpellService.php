<?php

declare(strict_types=1);

namespace App\Service\Spell;

use App\Entity\Hero\Hero;
use App\Entity\Hero\HeroSpell;
use App\Entity\Hero\SchoolMastery;
use App\Entity\Spell\Spell;
use App\Entity\Team\Team;
use App\Enum\School;
use App\Repository\Hero\HeroSpellRepository;
use App\Repository\Hero\SchoolMasteryRepository;
use App\Repository\Spell\SpellRepository;
use Doctrine\ORM\EntityManagerInterface;

class SpellService
{
    public function __construct(
        private readonly SpellRepository $spellRepository,
        private readonly HeroSpellRepository $heroSpellRepository,
        private readonly SchoolMasteryRepository $masteryRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * List the global spell library, optionally filtered by school and/or tier.
     *
     * @return list<Spell>
     */
    public function listLibrary(?School $school = null, ?int $tier = null): array
    {
        $criteria = [];
        if (null !== $school) {
            $criteria['school'] = $school;
        }
        if (null !== $tier) {
            $criteria['tier'] = $tier;
        }

        return $this->spellRepository->findBy($criteria, ['school' => 'ASC', 'tier' => 'ASC']);
    }

    /**
     * List all spells a hero knows (both equipped and unequipped).
     *
     * @return list<HeroSpell>
     */
    public function listForHero(Hero $hero): array
    {
        return $this->heroSpellRepository->findBy(['hero' => $hero], ['id' => 'ASC']);
    }

    /**
     * Learn a spell. Deducts gold and essence from the team.
     *
     * @throws \DomainException on prerequisite failure or duplicate
     * @throws \DomainException on insufficient resources
     */
    public function learn(Hero $hero, Spell $spell, Team $team): HeroSpell
    {
        // Duplicate check
        $existing = $this->heroSpellRepository->findOneBy(['hero' => $hero, 'spell' => $spell]);
        if (null !== $existing) {
            throw new \DomainException('Hero already knows this spell.');
        }

        // Mastery check
        $mastery = $this->getMasteryForSchool($hero, $spell->getSchool());
        $currentTier = $mastery?->getMasteryTier() ?? 0;
        if ($currentTier < $spell->getRequiredMasteryTier()) {
            throw new \DomainException(sprintf('Insufficient school mastery. Required tier: %d, current: %d.', $spell->getRequiredMasteryTier(), $currentTier));
        }

        // Cost check
        if ($team->getGold() < $spell->getLearningCostGold()) {
            throw new \DomainException(sprintf('Insufficient gold. Required: %d, available: %d.', $spell->getLearningCostGold(), $team->getGold()));
        }
        if ($team->getEssenceCommon() < $spell->getLearningCostEssence()) {
            throw new \DomainException(sprintf('Insufficient essence. Required: %d, available: %d.', $spell->getLearningCostEssence(), $team->getEssenceCommon()));
        }

        $team->setGold($team->getGold() - $spell->getLearningCostGold());
        $team->setEssenceCommon($team->getEssenceCommon() - $spell->getLearningCostEssence());

        $heroSpell = new HeroSpell();
        $heroSpell->setHero($hero);
        $heroSpell->setSpell($spell);

        $this->em->persist($heroSpell);
        $this->em->flush();

        return $heroSpell;
    }

    /**
     * Equip a learned spell to a slot number (1 to hero.magicCapacity).
     *
     * @throws \DomainException
     */
    public function equip(HeroSpell $heroSpell, int $slot): void
    {
        $hero = $heroSpell->getHero();
        $capacity = $hero->getMagicCapacity();

        if ($slot < 1 || $slot > $capacity) {
            throw new \DomainException(sprintf('Slot must be between 1 and %d (hero magic capacity).', $capacity));
        }

        // Unequip any spell already in that slot
        $existing = $this->heroSpellRepository->findOneBy([
            'hero' => $hero,
            'isEquipped' => true,
            'slotNumber' => $slot,
        ]);
        if (null !== $existing && $existing->getId() !== $heroSpell->getId()) {
            $existing->setIsEquipped(false);
            $existing->setSlotNumber(null);
        }

        $heroSpell->setIsEquipped(true);
        $heroSpell->setSlotNumber($slot);

        $this->em->flush();
    }

    /**
     * Unequip a spell from its current slot.
     *
     * @throws \DomainException
     */
    public function unequip(HeroSpell $heroSpell): void
    {
        if (!$heroSpell->isEquipped()) {
            throw new \DomainException('Spell is not equipped.');
        }

        $heroSpell->setIsEquipped(false);
        $heroSpell->setSlotNumber(null);

        $this->em->flush();
    }

    /**
     * Get or create a SchoolMastery record for a hero+school.
     * Used by the Training tick processor when magic training completes.
     */
    public function getOrCreateMastery(Hero $hero, School $school): SchoolMastery
    {
        $mastery = $this->getMasteryForSchool($hero, $school);
        if (null !== $mastery) {
            return $mastery;
        }

        $mastery = new SchoolMastery();
        $mastery->setHero($hero);
        $mastery->setSchool($school);

        $this->em->persist($mastery);
        $this->em->flush();

        return $mastery;
    }

    private function getMasteryForSchool(Hero $hero, School $school): ?SchoolMastery
    {
        return $this->masteryRepository->findOneBy(['hero' => $hero, 'school' => $school]);
    }

    /** @return array<string, mixed> */
    public function serializeSpell(Spell $spell): array
    {
        return [
            'id' => $spell->getId(),
            'name' => $spell->getName(),
            'school' => $spell->getSchool()->value,
            'tier' => $spell->getTier(),
            'type' => $spell->getType()->value,
            'effects' => $spell->getEffects(),
            'mana_cost' => $spell->getManaCost(),
            'cooldown' => $spell->getCooldown(),
            'required_mastery_tier' => $spell->getRequiredMasteryTier(),
            'learning_cost_gold' => $spell->getLearningCostGold(),
            'learning_cost_essence' => $spell->getLearningCostEssence(),
        ];
    }

    /** @return array<string, mixed> */
    public function serializeHeroSpell(HeroSpell $hs): array
    {
        return [
            'id' => $hs->getId(),
            'spell' => $this->serializeSpell($hs->getSpell()),
            'is_equipped' => $hs->isEquipped(),
            'slot_number' => $hs->getSlotNumber(),
        ];
    }
}
