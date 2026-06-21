<?php

declare(strict_types=1);

namespace App\Entity\Spell;

use App\Enum\School;
use App\Enum\SpellType;
use App\Repository\Spell\SpellRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SpellRepository::class)]
#[ORM\Table(name: 'spell')]
class Spell
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(length: 10, enumType: School::class)]
    private School $school;

    #[ORM\Column]
    private int $tier;

    #[ORM\Column(length: 15, enumType: SpellType::class)]
    private SpellType $type;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $effects = [];

    #[ORM\Column]
    private int $manaCost;

    #[ORM\Column]
    private int $cooldown;

    #[ORM\Column]
    private int $requiredMasteryTier;

    #[ORM\Column]
    private int $learningCostGold;

    #[ORM\Column]
    private int $learningCostEssence;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getSchool(): School
    {
        return $this->school;
    }

    public function setSchool(School $school): static
    {
        $this->school = $school;

        return $this;
    }

    public function getTier(): int
    {
        return $this->tier;
    }

    public function setTier(int $tier): static
    {
        $this->tier = $tier;

        return $this;
    }

    public function getType(): SpellType
    {
        return $this->type;
    }

    public function setType(SpellType $type): static
    {
        $this->type = $type;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getEffects(): array
    {
        return $this->effects;
    }

    /** @param array<string, mixed> $effects */
    public function setEffects(array $effects): static
    {
        $this->effects = $effects;

        return $this;
    }

    public function getManaCost(): int
    {
        return $this->manaCost;
    }

    public function setManaCost(int $manaCost): static
    {
        $this->manaCost = $manaCost;

        return $this;
    }

    public function getCooldown(): int
    {
        return $this->cooldown;
    }

    public function setCooldown(int $cooldown): static
    {
        $this->cooldown = $cooldown;

        return $this;
    }

    public function getRequiredMasteryTier(): int
    {
        return $this->requiredMasteryTier;
    }

    public function setRequiredMasteryTier(int $v): static
    {
        $this->requiredMasteryTier = $v;

        return $this;
    }

    public function getLearningCostGold(): int
    {
        return $this->learningCostGold;
    }

    public function setLearningCostGold(int $v): static
    {
        $this->learningCostGold = $v;

        return $this;
    }

    public function getLearningCostEssence(): int
    {
        return $this->learningCostEssence;
    }

    public function setLearningCostEssence(int $v): static
    {
        $this->learningCostEssence = $v;

        return $this;
    }
}
