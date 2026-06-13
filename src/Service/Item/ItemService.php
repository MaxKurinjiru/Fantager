<?php

declare(strict_types=1);

namespace App\Service\Item;

use App\Entity\Hero\Hero;
use App\Entity\Item\Item;
use App\Entity\Team\Team;
use App\Enum\ItemRarity;
use App\Enum\ItemSlotType;
use App\Enum\ItemStatus;
use App\Repository\Item\ItemRepository;
use Doctrine\ORM\EntityManagerInterface;

class ItemService
{
    /**
     * Essence granted per rarity tier on dismantle.
     * Keys match ItemRarity values; values are amounts of same-tier essence.
     */
    private const DISMANTLE_ESSENCE = [
        'common' => 5,
        'uncommon' => 5,
        'rare' => 4,
        'epic' => 3,
        'legendary' => 2,
        'mythic' => 1,
    ];

    /** Gold cost per missing durability point by rarity. */
    private const REPAIR_COST_PER_POINT = [
        'common' => 2,
        'uncommon' => 5,
        'rare' => 10,
        'epic' => 20,
        'legendary' => 40,
        'mythic' => 80,
    ];

    public function __construct(
        private readonly ItemRepository $itemRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** @return array<int, Item> */
    public function listByTeam(Team $team, ?Hero $hero = null): array
    {
        $criteria = ['ownerTeam' => $team];
        if (null !== $hero) {
            $criteria['equippedHero'] = $hero;
        }

        return $this->itemRepository->findBy($criteria, ['id' => 'ASC']);
    }

    public function findForTeam(int $id, Team $team): ?Item
    {
        return $this->itemRepository->findOneBy(['id' => $id, 'ownerTeam' => $team]);
    }

    /**
     * Equip an item to a hero's slot. Any item previously in that slot is unequipped.
     *
     * @throws \DomainException
     */
    public function equip(Item $item, Hero $hero, ItemSlotType $slot): void
    {
        if ($item->getStatus() !== ItemStatus::Available) {
            throw new \DomainException('Listed or unavailable items cannot be equipped.');
        }

        if ($item->getOwnerTeam()->getId() !== $hero->getTeam()->getId()) {
            throw new \DomainException('Item does not belong to the same team as the hero.');
        }

        if ($item->getSlotType() !== $slot) {
            throw new \DomainException(sprintf('Item slot type "%s" cannot be equipped in slot "%s".', $item->getSlotType()->value, $slot->value));
        }

        // Unequip whatever is currently in that slot for this hero
        $existing = $this->itemRepository->findOneBy([
            'equippedHero' => $hero,
            'equippedSlot' => $slot,
        ]);
        if (null !== $existing) {
            $existing->setEquippedHero(null);
            $existing->setEquippedSlot(null);
        }

        // Unequip from previous hero if item was equipped elsewhere
        if (null !== $item->getEquippedHero()) {
            $item->setEquippedHero(null);
            $item->setEquippedSlot(null);
        }

        $item->setEquippedHero($hero);
        $item->setEquippedSlot($slot);

        $this->em->flush();
    }

    /**
     * Unequip an item from its current hero.
     *
     * @throws \DomainException
     */
    public function unequip(Item $item): void
    {
        if (null === $item->getEquippedHero()) {
            throw new \DomainException('Item is not equipped.');
        }

        $item->setEquippedHero(null);
        $item->setEquippedSlot(null);

        $this->em->flush();
    }

    /**
     * Dismantle an item: delete it and return essence to the team.
     *
     * @return array{rarity: string, essence_amount: int}
     *
     * @throws \DomainException when item is currently equipped
     */
    public function dismantle(Item $item, Team $team): array
    {
        if ($item->getStatus() !== ItemStatus::Available) {
            throw new \DomainException('Listed or unavailable items cannot be dismantled.');
        }

        if ($item->getOwnerTeam()->getId() !== $team->getId()) {
            throw new \DomainException('Item does not belong to your team.');
        }

        if (null !== $item->getEquippedHero()) {
            throw new \DomainException('Cannot dismantle an equipped item. Unequip it first.');
        }

        $rarity = $item->getRarity();
        $amount = self::DISMANTLE_ESSENCE[$rarity->value] ?? 1;
        $this->addEssenceByRarity($team, $rarity, $amount);

        $this->em->remove($item);
        $this->em->flush();

        return ['rarity' => $rarity->value, 'essence_amount' => $amount];
    }

    /**
     * Repair durability to 100. Gold cost scales with rarity and missing durability.
     *
     * @throws \DomainException on insufficient gold
     */
    public function repair(Item $item, Team $team): int
    {
        if ($item->getStatus() !== ItemStatus::Available) {
            throw new \DomainException('Listed or unavailable items cannot be repaired.');
        }

        if ($item->getOwnerTeam()->getId() !== $team->getId()) {
            throw new \DomainException('Item does not belong to your team.');
        }

        $missing = 100 - $item->getDurability();
        if (0 === $missing) {
            return 0;
        }

        $ratePerPoint = self::REPAIR_COST_PER_POINT[$item->getRarity()->value] ?? 2;
        $cost = $missing * $ratePerPoint;

        if ($team->getGold() < $cost) {
            throw new \DomainException(sprintf('Insufficient gold. Repair costs %d, available: %d.', $cost, $team->getGold()));
        }

        $team->setGold($team->getGold() - $cost);
        $item->setDurability(100);

        $this->em->flush();

        return $cost;
    }

    public function calculateRepairCost(Item $item): int
    {
        $missing = 100 - $item->getDurability();
        $ratePerPoint = self::REPAIR_COST_PER_POINT[$item->getRarity()->value] ?? 2;

        return $missing * $ratePerPoint;
    }

    /** @return array<string, mixed> */
    public function serialize(Item $item): array
    {
        return [
            'id' => $item->getId(),
            'name' => $item->getName(),
            'slot_type' => $item->getSlotType()->value,
            'category' => $item->getCategory()->value,
            'rarity' => $item->getRarity()->value,
            'durability' => $item->getDurability(),
            'repair_cost' => $this->calculateRepairCost($item),
            'bonuses' => $item->getBonuses(),
            'special_effects' => $item->getSpecialEffects(),
            'equipped_hero_id' => $item->getEquippedHero()?->getId(),
            'equipped_slot' => $item->getEquippedSlot()?->value,
        ];
    }

    private function addEssenceByRarity(Team $team, ItemRarity $rarity, int $amount): void
    {
        match ($rarity) {
            ItemRarity::Common => $team->setEssenceCommon($team->getEssenceCommon() + $amount),
            ItemRarity::Uncommon => $team->setEssenceUncommon($team->getEssenceUncommon() + $amount),
            ItemRarity::Rare => $team->setEssenceRare($team->getEssenceRare() + $amount),
            ItemRarity::Epic => $team->setEssenceEpic($team->getEssenceEpic() + $amount),
            ItemRarity::Legendary => $team->setEssenceLegendary($team->getEssenceLegendary() + $amount),
            ItemRarity::Mythic => $team->setEssenceMythic($team->getEssenceMythic() + $amount),
        };
    }
}
