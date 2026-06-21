<?php

declare(strict_types=1);

namespace App\Entity\League;

use App\Entity\Team\Team;
use App\Repository\League\LeagueStandingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LeagueStandingRepository::class)]
#[ORM\Table(name: 'league_standing')]
#[ORM\UniqueConstraint(name: 'UNIQ_GROUP_TEAM', columns: ['league_group_id', 'team_id'])]
class LeagueStanding
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'standings')]
    #[ORM\JoinColumn(name: 'league_group_id', nullable: false)]
    private LeagueGroup $group;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Team $team;

    #[ORM\Column(options: ['default' => 0])]
    private int $played = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $wins = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $draws = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $losses = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $points = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $goalDifference = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGroup(): LeagueGroup
    {
        return $this->group;
    }

    public function setGroup(LeagueGroup $group): static
    {
        $this->group = $group;

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

    public function getPlayed(): int
    {
        return $this->played;
    }

    public function setPlayed(int $played): static
    {
        $this->played = $played;

        return $this;
    }

    public function getWins(): int
    {
        return $this->wins;
    }

    public function setWins(int $wins): static
    {
        $this->wins = $wins;

        return $this;
    }

    public function getDraws(): int
    {
        return $this->draws;
    }

    public function setDraws(int $draws): static
    {
        $this->draws = $draws;

        return $this;
    }

    public function getLosses(): int
    {
        return $this->losses;
    }

    public function setLosses(int $losses): static
    {
        $this->losses = $losses;

        return $this;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function setPoints(int $points): static
    {
        $this->points = $points;

        return $this;
    }

    public function getGoalDifference(): int
    {
        return $this->goalDifference;
    }

    public function setGoalDifference(int $goalDifference): static
    {
        $this->goalDifference = $goalDifference;

        return $this;
    }
}
