<?php

declare(strict_types=1);

namespace App\Entity\FriendlyMatch;

use App\Entity\Combat\Battle;
use App\Entity\Formation\Formation;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;
use App\Enum\FriendlyMatchStatus;
use App\Repository\FriendlyMatch\FriendlyMatchRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FriendlyMatchRepository::class)]
#[ORM\Table(name: 'friendly_match')]
class FriendlyMatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Kingdom $kingdom;

    /** Home team = challenger (invite sender). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Team $homeTeam;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Team $awayTeam;

    #[ORM\Column]
    private \DateTimeImmutable $scheduledAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, unique: true, onDelete: 'SET NULL')]
    private ?Battle $battle = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Formation $homeFormation = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Formation $awayFormation = null;

    #[ORM\Column(length: 20, enumType: FriendlyMatchStatus::class)]
    private FriendlyMatchStatus $status = FriendlyMatchStatus::Pending;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'responded_at', nullable: true)]
    private ?\DateTimeImmutable $respondedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

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

    public function getStatus(): FriendlyMatchStatus
    {
        return $this->status;
    }

    public function setStatus(FriendlyMatchStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getRespondedAt(): ?\DateTimeImmutable
    {
        return $this->respondedAt;
    }

    public function setRespondedAt(?\DateTimeImmutable $respondedAt): static
    {
        $this->respondedAt = $respondedAt;

        return $this;
    }

    public function isParticipant(Team $team): bool
    {
        return $this->homeTeam->getId() === $team->getId()
            || $this->awayTeam->getId() === $team->getId();
    }
}
