<?php

declare(strict_types=1);

namespace App\Entity\Dungeon;

use App\Entity\Formation\Formation;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;
use App\Enum\DungeonResult;
use App\Repository\Dungeon\DungeonRunRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DungeonRunRepository::class)]
#[ORM\Table(name: 'dungeon_run')]
class DungeonRun
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Kingdom $kingdom;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Team $team;

    #[ORM\Column(length: 100)]
    private string $dungeonKey;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Formation $formation = null;

    #[ORM\Column(length: 15, enumType: DungeonResult::class, nullable: true)]
    private ?DungeonResult $result = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $rewardsXp = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $rewardsEssence = 0;

    #[ORM\Column(type: 'json')]
    private array $rewardsItems = [];

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKingdom(): Kingdom
    {
        return $this->kingdom;
    }

    public function setKingdom(Kingdom $kingdom): static
    {
        $this->kingdom = $kingdom;

        return $this;
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

    public function getDungeonKey(): string
    {
        return $this->dungeonKey;
    }

    public function setDungeonKey(string $dungeonKey): static
    {
        $this->dungeonKey = $dungeonKey;

        return $this;
    }

    public function getFormation(): ?Formation
    {
        return $this->formation;
    }

    public function setFormation(?Formation $formation): static
    {
        $this->formation = $formation;

        return $this;
    }

    public function getResult(): ?DungeonResult
    {
        return $this->result;
    }

    public function setResult(?DungeonResult $result): static
    {
        $this->result = $result;

        return $this;
    }

    public function getRewardsXp(): int
    {
        return $this->rewardsXp;
    }

    public function setRewardsXp(int $rewardsXp): static
    {
        $this->rewardsXp = $rewardsXp;

        return $this;
    }

    public function getRewardsEssence(): int
    {
        return $this->rewardsEssence;
    }

    public function setRewardsEssence(int $rewardsEssence): static
    {
        $this->rewardsEssence = $rewardsEssence;

        return $this;
    }

    public function getRewardsItems(): array
    {
        return $this->rewardsItems;
    }

    public function setRewardsItems(array $rewardsItems): static
    {
        $this->rewardsItems = $rewardsItems;

        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;

        return $this;
    }
}
