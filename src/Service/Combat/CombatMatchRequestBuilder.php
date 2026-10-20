<?php

declare(strict_types=1);

namespace App\Service\Combat;

use App\Entity\Formation\Formation;
use App\Entity\Formation\FormationSlot;
use App\Entity\Hero\Hero;
use App\Enum\FormationPosition;
use App\Enum\HeroTrait;
use App\Enum\ItemSlotType;
use App\Enum\ItemSubType;
use App\Enum\MatchType;
use App\Repository\Item\ItemRepository;
use App\Service\Spell\SpellService;
use App\ValueObject\Combat\CombatantSnapshot;
use App\ValueObject\Combat\CombatMatchRequest;
use App\ValueObject\Combat\CombatSide;

/**
 * Builds a {@see CombatMatchRequest} from two fully slotted formations.
 */
class CombatMatchRequestBuilder
{
    public function __construct(
        private readonly CombatStatCalculator $combatStatCalculator,
        private readonly ItemRepository $itemRepository,
        private readonly SpellService $spellService,
    ) {
    }

    public function fromFormations(
        Formation $formationA,
        Formation $formationB,
        MatchType $matchType,
        int $seed,
        int $engineVersion = CombatEngine::ENGINE_VERSION,
    ): CombatMatchRequest {
        return new CombatMatchRequest(
            $this->buildSide($formationA),
            $this->buildSide($formationB),
            $matchType,
            $seed,
            $engineVersion,
        );
    }

    private function buildSide(Formation $formation): CombatSide
    {
        $teamId = $formation->getTeam()->getId();
        if (null === $teamId) {
            throw new \InvalidArgumentException('Formation team must be persisted (have an id).');
        }

        /** @var array<string, FormationSlot> $slotsByPosition */
        $slotsByPosition = [];
        foreach ($formation->getSlots() as $slot) {
            $slotsByPosition[$slot->getPosition()->value] = $slot;
        }

        $raceCounts = [];
        foreach (FormationPosition::cases() as $position) {
            $slot = $slotsByPosition[$position->value] ?? null;
            $hero = $slot?->getHero();
            if (!$slot instanceof FormationSlot || !$hero instanceof Hero) {
                throw new \InvalidArgumentException(sprintf('Formation %d is missing a hero in slot %s.', $formation->getId() ?? 0, $position->value));
            }
            $raceCounts[$hero->getRace()->value] = ($raceCounts[$hero->getRace()->value] ?? 0) + 1;
        }

        $combatants = [];
        foreach (FormationPosition::cases() as $position) {
            $slot = $slotsByPosition[$position->value];
            $combatants[] = $this->buildCombatant($slot, $raceCounts);
        }

        return new CombatSide(
            $teamId,
            $formation->getId(),
            $formation->getApproach(),
            $combatants,
        );
    }

    /**
     * @param array<string, int> $raceCounts
     */
    private function buildCombatant(FormationSlot $slot, array $raceCounts): CombatantSnapshot
    {
        /** @var Hero $hero */
        $hero = $slot->getHero();
        $heroId = $hero->getId();
        if (null === $heroId) {
            throw new \InvalidArgumentException('Hero must be persisted (have an id).');
        }

        $spells = [];
        foreach ($hero->getHeroSpells() as $heroSpell) {
            if (!$heroSpell->isEquipped()) {
                continue;
            }

            $spell = $heroSpell->getSpell();
            $spellId = $spell->getId();
            if (null === $spellId) {
                continue;
            }

            $spells[] = [
                'id' => $spellId,
                'name' => $this->spellService->displayName($spell),
                'school' => $spell->getSchool()->value,
                'type' => $spell->getType()->value,
                'mana_cost' => $spell->getManaCost(),
                'cooldown' => $spell->getCooldown(),
                'effects' => $spell->getEffects(),
                'tier' => $spell->getTier(),
                'requires_magical_weapon' => $spell->requiresMagicalWeapon(),
            ];
        }

        /** @var list<mixed> $spellPriorities */
        $spellPriorities = $slot->getSpellPriorities();

        $equippedItems = $this->itemRepository->findBy(['equippedHero' => $hero]);
        $weaponSubTypes = [
            ItemSubType::OneHandedSword,
            ItemSubType::TwoHandedSword,
            ItemSubType::OneHandedAxe,
            ItemSubType::TwoHandedAxe,
            ItemSubType::OneHandedMace,
            ItemSubType::TwoHandedMace,
            ItemSubType::Dagger,
            ItemSubType::Bow,
            ItemSubType::Crossbow,
            ItemSubType::Wand,
            ItemSubType::Staff,
        ];
        $mainHandWeapon = null;
        $fallbackWeapon = null;
        $armorSubType = null;
        foreach ($equippedItems as $item) {
            $subType = $item->getSubType();
            if (null !== $subType && in_array($subType, $weaponSubTypes, true)) {
                $fallbackWeapon ??= $subType;
                if (ItemSlotType::MainHand === $item->getEquippedSlot()) {
                    $mainHandWeapon = $subType;
                }
            }
            if (null === $armorSubType && in_array($subType, [
                ItemSubType::LightArmor,
                ItemSubType::MediumArmor,
                ItemSubType::HeavyArmor,
            ], true)) {
                $armorSubType = $subType;
            }
        }
        $weaponSubType = $mainHandWeapon ?? $fallbackWeapon;

        $derived = $this->combatStatCalculator->calculate($hero);
        $trait = $hero->getTrait();
        if (HeroTrait::Loner === $trait && 1 === ($raceCounts[$hero->getRace()->value] ?? 0)) {
            $derived = $derived->withOutgoingDamageMultiplier(
                $derived->getOutgoingDamageMultiplier() * $trait->getSoleRaceDamageMultiplier()
            );
        }

        return new CombatantSnapshot(
            $heroId,
            $hero->getName(),
            $slot->getPosition(),
            $hero->getRace(),
            $hero->getLevel(),
            $hero->getForm(),
            $hero->getFatigue(),
            $hero->getMorale(),
            $derived,
            $slot->getStrategy(),
            $spellPriorities,
            $spells,
            $weaponSubType,
            $armorSubType,
        );
    }
}
