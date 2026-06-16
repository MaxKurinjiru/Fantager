<?php

declare(strict_types=1);

namespace App\Entity\Team;

use App\Entity\Hero\Hero;
use App\Enum\Race;
use App\Repository\Team\TeamSummonHistoryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TeamSummonHistoryRepository::class)]
#[ORM\Table(name: 'team_summon_history')]
class TeamSummonHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Team $team;

    #[ORM\Column(length: 10, enumType: Race::class)]
    private Race $raceSelected;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Hero $hero = null;

    #[ORM\Column]
    private int $goldCost;

    #[ORM\Column]
    private \DateTimeImmutable $summonedAt;

    public function __construct()
    {
        $this->summonedAt = new \DateTimeImmutable();
    }

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

    public function getRaceSelected(): Race
    {
        return $this->raceSelected;
    }

    public function setRaceSelected(Race $raceSelected): static
    {
        $this->raceSelected = $raceSelected;

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

    public function getGoldCost(): int
    {
        return $this->goldCost;
    }

    public function setGoldCost(int $goldCost): static
    {
        $this->goldCost = $goldCost;

        return $this;
    }

    public function getSummonedAt(): \DateTimeImmutable
    {
        return $this->summonedAt;
    }
}
