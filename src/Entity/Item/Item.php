<?php

declare(strict_types=1);

namespace App\Entity\Item;

use App\Entity\Hero\Hero;
use App\Entity\Team\Team;
use App\Enum\ItemCategory;
use App\Enum\ItemRarity;
use App\Enum\ItemSlotType;
use App\Enum\ItemStatus;
use App\Repository\Item\ItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ItemRepository::class)]
#[ORM\Table(name: 'item')]
class Item
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Team $ownerTeam;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Hero $equippedHero = null;

    #[ORM\Column(length: 15, enumType: ItemSlotType::class, nullable: true)]
    private ?ItemSlotType $equippedSlot = null;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(length: 15, enumType: ItemSlotType::class)]
    private ItemSlotType $slotType;

    #[ORM\Column(length: 20, enumType: ItemCategory::class)]
    private ItemCategory $category;

    #[ORM\Column(length: 15, enumType: ItemRarity::class)]
    private ItemRarity $rarity;

    #[ORM\Column(options: ['default' => 100])]
    private int $durability = 100;

    #[ORM\Column(type: 'json')]
    private array $bonuses = [];

    #[ORM\Column(type: 'json')]
    private array $specialEffects = [];

    #[ORM\Column(length: 15, enumType: ItemStatus::class, options: ['default' => 'available'])]
    private ItemStatus $status = ItemStatus::Available;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwnerTeam(): Team
    {
        return $this->ownerTeam;
    }

    public function setOwnerTeam(Team $ownerTeam): static
    {
        $this->ownerTeam = $ownerTeam;

        return $this;
    }

    public function getEquippedHero(): ?Hero
    {
        return $this->equippedHero;
    }

    public function setEquippedHero(?Hero $equippedHero): static
    {
        $this->equippedHero = $equippedHero;

        return $this;
    }

    public function getEquippedSlot(): ?ItemSlotType
    {
        return $this->equippedSlot;
    }

    public function setEquippedSlot(?ItemSlotType $equippedSlot): static
    {
        $this->equippedSlot = $equippedSlot;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSlotType(): ItemSlotType
    {
        return $this->slotType;
    }

    public function setSlotType(ItemSlotType $slotType): static
    {
        $this->slotType = $slotType;

        return $this;
    }

    public function getCategory(): ItemCategory
    {
        return $this->category;
    }

    public function setCategory(ItemCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getRarity(): ItemRarity
    {
        return $this->rarity;
    }

    public function setRarity(ItemRarity $rarity): static
    {
        $this->rarity = $rarity;

        return $this;
    }

    public function getDurability(): int
    {
        return $this->durability;
    }

    public function setDurability(int $durability): static
    {
        $this->durability = $durability;

        return $this;
    }

    public function getBonuses(): array
    {
        return $this->bonuses;
    }

    public function setBonuses(array $bonuses): static
    {
        $this->bonuses = $bonuses;

        return $this;
    }

    public function getSpecialEffects(): array
    {
        return $this->specialEffects;
    }

    public function setSpecialEffects(array $specialEffects): static
    {
        $this->specialEffects = $specialEffects;

        return $this;
    }

    public function getStatus(): ItemStatus
    {
        return $this->status;
    }

    public function setStatus(ItemStatus $status): static
    {
        $this->status = $status;

        return $this;
    }
}
