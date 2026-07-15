<?php

declare(strict_types=1);

namespace App\Service\Combat;

use App\Entity\Formation\Formation;
use App\Entity\Formation\FormationSlot;
use App\Entity\Hero\Hero;
use App\Enum\FormationPosition;
use App\Enum\MatchType;
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

        $slotsByPosition = [];
        foreach ($formation->getSlots() as $slot) {
            $slotsByPosition[$slot->getPosition()->value] = $slot;
        }

        $combatants = [];
        foreach (FormationPosition::cases() as $position) {
            $slot = $slotsByPosition[$position->value] ?? null;
            if (!$slot instanceof FormationSlot || null === $slot->getHero()) {
                throw new \InvalidArgumentException(sprintf('Formation %d is missing a hero in slot %s.', $formation->getId() ?? 0, $position->value));
            }

            $combatants[] = $this->buildCombatant($slot);
        }

        return new CombatSide(
            $teamId,
            $formation->getId(),
            $formation->getApproach(),
            $combatants,
        );
    }

    private function buildCombatant(FormationSlot $slot): CombatantSnapshot
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
                'name' => $spell->getName(),
                'school' => $spell->getSchool()->value,
                'type' => $spell->getType()->value,
                'mana_cost' => $spell->getManaCost(),
                'cooldown' => $spell->getCooldown(),
            ];
        }

        /** @var list<mixed> $spellPriorities */
        $spellPriorities = $slot->getSpellPriorities();

        return new CombatantSnapshot(
            $heroId,
            $hero->getName(),
            $slot->getPosition(),
            $hero->getRace(),
            $hero->getLevel(),
            $hero->getForm(),
            $hero->getFatigue(),
            $hero->getMorale(),
            $this->combatStatCalculator->calculate($hero),
            $slot->getStrategy(),
            $spellPriorities,
            $spells,
        );
    }
}
