<?php

declare(strict_types=1);

namespace App\Entity\Crafting;

use App\Entity\Team\Team;
use App\Enum\CraftingStatus;
use App\Repository\Crafting\CraftingQueueRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CraftingQueueRepository::class)]
#[ORM\Table(name: 'crafting_queue')]
class CraftingQueue
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Team $team;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private CraftingRecipe $recipe;

    #[ORM\Column(length: 15, enumType: CraftingStatus::class)]
    private CraftingStatus $status = CraftingStatus::Pending;

    #[ORM\Column]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column]
    private \DateTimeImmutable $completesAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTeam(): Team
    {
        return $this->team;
    }

    public function setTeam(Team $team): static
    {
        $this->team = $team;

        return $this;
    }

    public function getRecipe(): CraftingRecipe
    {
        return $this->recipe;
    }

    public function setRecipe(CraftingRecipe $recipe): static
    {
        $this->recipe = $recipe;

        return $this;
    }

    public function getStatus(): CraftingStatus
    {
        return $this->status;
    }

    public function setStatus(CraftingStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(\DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getCompletesAt(): \DateTimeImmutable
    {
        return $this->completesAt;
    }

    public function setCompletesAt(\DateTimeImmutable $completesAt): static
    {
        $this->completesAt = $completesAt;

        return $this;
    }
}
