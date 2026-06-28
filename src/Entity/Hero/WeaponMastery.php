<?php

declare(strict_types=1);

namespace App\Entity\Hero;

use App\Enum\ItemSubType;
use App\Repository\Hero\WeaponMasteryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WeaponMasteryRepository::class)]
#[ORM\Table(name: 'hero_weapon_mastery')]
#[ORM\UniqueConstraint(name: 'UNIQ_HERO_WEAPON_STYLE', columns: ['hero_id', 'style'])]
class WeaponMastery
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Hero::class, inversedBy: 'weaponMasteries')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Hero $hero;

    #[ORM\Column(length: 30, enumType: ItemSubType::class)]
    private ItemSubType $style;

    #[ORM\Column(options: ['default' => 1])]
    private int $masteryTier = 1;

    #[ORM\Column(options: ['default' => 0])]
    private int $xp = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $attunementProgress = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHero(): Hero
    {
        return $this->hero;
    }

    public function setHero(Hero $hero): static
    {
        $this->hero = $hero;

        return $this;
    }

    public function getStyle(): ItemSubType
    {
        return $this->style;
    }

    public function setStyle(ItemSubType $style): static
    {
        $this->style = $style;

        return $this;
    }

    public function getMasteryTier(): int
    {
        return $this->masteryTier;
    }

    public function setMasteryTier(int $masteryTier): static
    {
        $this->masteryTier = $masteryTier;

        return $this;
    }

    public function getXp(): int
    {
        return $this->xp;
    }

    public function setXp(int $xp): static
    {
        $this->xp = $xp;

        return $this;
    }

    public function getAttunementProgress(): int
    {
        return $this->attunementProgress;
    }

    public function setAttunementProgress(int $attunementProgress): static
    {
        $this->attunementProgress = $attunementProgress;

        return $this;
    }
}
