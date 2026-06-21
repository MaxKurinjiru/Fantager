<?php

declare(strict_types=1);

namespace App\Entity\League;

use App\Entity\Combat\Battle;
use App\Entity\Formation\Formation;
use App\Entity\Team\Team;
use App\Enum\LeagueFixtureStatus;
use App\Repository\League\LeagueFixtureRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LeagueFixtureRepository::class)]
#[ORM\Table(name: 'league_fixture')]
class LeagueFixture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'fixtures')]
    #[ORM\JoinColumn(name: 'league_group_id', nullable: false)]
    private LeagueGroup $group;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Team $homeTeam;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Team $awayTeam;

    #[ORM\Column]
    private \DateTimeImmutable $scheduledAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Battle $battle = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Formation $homeFormation = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Formation $awayFormation = null;

    #[ORM\Column(length: 20, enumType: LeagueFixtureStatus::class)]
    private LeagueFixtureStatus $status = LeagueFixtureStatus::Scheduled;

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

    public function getHomeTeam(): Team
    {
        return $this->homeTeam;
    }

    public function setHomeTeam(Team $homeTeam): static
    {
        $this->homeTeam = $homeTeam;

        return $this;
    }

    public function getAwayTeam(): Team
    {
        return $this->awayTeam;
    }

    public function setAwayTeam(Team $awayTeam): static
    {
        $this->awayTeam = $awayTeam;

        return $this;
    }

    public function getScheduledAt(): \DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(\DateTimeImmutable $scheduledAt): static
    {
        $this->scheduledAt = $scheduledAt;

        return $this;
    }

    public function getBattle(): ?Battle
    {
        return $this->battle;
    }

    public function setBattle(?Battle $battle): static
    {
        $this->battle = $battle;

        return $this;
    }

    public function getHomeFormation(): ?Formation
    {
        return $this->homeFormation;
    }

    public function setHomeFormation(?Formation $homeFormation): static
    {
        $this->homeFormation = $homeFormation;

        return $this;
    }

    public function getAwayFormation(): ?Formation
    {
        return $this->awayFormation;
    }

    public function setAwayFormation(?Formation $awayFormation): static
    {
        $this->awayFormation = $awayFormation;

        return $this;
    }

    public function getStatus(): LeagueFixtureStatus
    {
        return $this->status;
    }

    public function setStatus(LeagueFixtureStatus $status): static
    {
        $this->status = $status;

        return $this;
    }
}
