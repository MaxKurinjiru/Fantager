<?php

declare(strict_types=1);

namespace App\Entity\Crafting;

use App\Enum\ItemCategory;
use App\Enum\ItemRarity;
use App\Repository\Crafting\CraftingRecipeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CraftingRecipeRepository::class)]
#[ORM\Table(name: 'crafting_recipe')]
class CraftingRecipe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, enumType: ItemCategory::class)]
    private ItemCategory $resultItemCategory;

    #[ORM\Column(length: 15, enumType: ItemRarity::class)]
    private ItemRarity $resultItemRarity;

    #[ORM\Column(type: 'json')]
    private array $requiredMaterials = [];

    #[ORM\Column(length: 15, enumType: ItemRarity::class, nullable: true)]
    private ?ItemRarity $essenceCostType = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $essenceCostAmount = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $goldCost = 0;

    #[ORM\Column(type: 'decimal', precision: 4, scale: 2, options: ['default' => '1.00'])]
    private string $successRateBase = '1.00';

    #[ORM\Column]
    private int $craftingTime;

    #[ORM\Column(options: ['default' => 1])]
    private int $requiredForgeLevel = 1;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getResultItemCategory(): ItemCategory
    {
        return $this->resultItemCategory;
    }

    public function setResultItemCategory(ItemCategory $v): static
    {
        $this->resultItemCategory = $v;

        return $this;
    }

    public function getResultItemRarity(): ItemRarity
    {
        return $this->resultItemRarity;
    }

    public function setResultItemRarity(ItemRarity $v): static
    {
        $this->resultItemRarity = $v;

        return $this;
    }

    public function getRequiredMaterials(): array
    {
        return $this->requiredMaterials;
    }

    public function setRequiredMaterials(array $v): static
    {
        $this->requiredMaterials = $v;

        return $this;
    }

    public function getEssenceCostType(): ?ItemRarity
    {
        return $this->essenceCostType;
    }

    public function setEssenceCostType(?ItemRarity $v): static
    {
        $this->essenceCostType = $v;

        return $this;
    }

    public function getEssenceCostAmount(): int
    {
        return $this->essenceCostAmount;
    }

    public function setEssenceCostAmount(int $v): static
    {
        $this->essenceCostAmount = $v;

        return $this;
    }

    public function getGoldCost(): int
    {
        return $this->goldCost;
    }

    public function setGoldCost(int $goldCost): static
    {
        $this->goldCost = $goldCost;

        return $this;
    }

    public function getSuccessRateBase(): string
    {
        return $this->successRateBase;
    }

    public function setSuccessRateBase(string $v): static
    {
        $this->successRateBase = $v;

        return $this;
    }

    public function getCraftingTime(): int
    {
        return $this->craftingTime;
    }

    public function setCraftingTime(int $craftingTime): static
    {
        $this->craftingTime = $craftingTime;

        return $this;
    }

    public function getRequiredForgeLevel(): int
    {
        return $this->requiredForgeLevel;
    }

    public function setRequiredForgeLevel(int $v): static
    {
        $this->requiredForgeLevel = $v;

        return $this;
    }
}
