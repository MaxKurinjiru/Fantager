<?php

declare(strict_types=1);

namespace App\Entity\Hero;

use App\Entity\Spell\Spell;
use App\Repository\Hero\HeroSpellRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HeroSpellRepository::class)]
#[ORM\Table(name: 'hero_spell')]
class HeroSpell
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'heroSpells')]
    #[ORM\JoinColumn(nullable: false)]
    private Hero $hero;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Spell $spell;

    #[ORM\Column(options: ['default' => false])]
    private bool $isEquipped = false;

    #[ORM\Column(nullable: true)]
    private ?int $slotNumber = null;

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

    public function getSpell(): Spell
    {
        return $this->spell;
    }

    public function setSpell(Spell $spell): static
    {
        $this->spell = $spell;

        return $this;
    }

    public function isEquipped(): bool
    {
        return $this->isEquipped;
    }

    public function setIsEquipped(bool $isEquipped): static
    {
        $this->isEquipped = $isEquipped;

        return $this;
    }

    public function getSlotNumber(): ?int
    {
        return $this->slotNumber;
    }

    public function setSlotNumber(?int $slotNumber): static
    {
        $this->slotNumber = $slotNumber;

        return $this;
    }
}
