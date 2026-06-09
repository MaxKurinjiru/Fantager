<?php

declare(strict_types=1);

namespace App\Entity\Formation;

use App\Entity\Hero\Hero;
use App\Enum\FormationPosition;
use App\Repository\Formation\FormationSlotRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FormationSlotRepository::class)]
#[ORM\Table(name: 'formation_slot')]
#[ORM\UniqueConstraint(name: 'UNIQ_FORMATION_POSITION', columns: ['formation_id', 'position'])]
class FormationSlot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'slots')]
    #[ORM\JoinColumn(nullable: false)]
    private Formation $formation;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Hero $hero = null;

    #[ORM\Column(length: 10, enumType: FormationPosition::class)]
    private FormationPosition $position;

    #[ORM\Column(type: 'json')]
    private array $strategy = [];

    #[ORM\Column(type: 'json')]
    private array $spellPriorities = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFormation(): Formation
    {
        return $this->formation;
    }

    public function setFormation(Formation $formation): static
    {
        $this->formation = $formation;

        return $this;
    }

    public function getHero(): ?Hero
    {
        return $this->hero;
    }

    public function setHero(?Hero $hero): static
    {
        $this->hero = $hero;

        return $this;
    }

    public function getPosition(): FormationPosition
    {
        return $this->position;
    }

    public function setPosition(FormationPosition $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getStrategy(): array
    {
        return $this->strategy;
    }

    public function setStrategy(array $strategy): static
    {
        $this->strategy = $strategy;

        return $this;
    }

    public function getSpellPriorities(): array
    {
        return $this->spellPriorities;
    }

    public function setSpellPriorities(array $spellPriorities): static
    {
        $this->spellPriorities = $spellPriorities;

        return $this;
    }
}
