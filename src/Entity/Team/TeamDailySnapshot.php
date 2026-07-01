<?php

declare(strict_types=1);

namespace App\Entity\Team;

use App\Repository\Team\TeamDailySnapshotRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TeamDailySnapshotRepository::class)]
#[ORM\Table(name: 'team_daily_snapshot')]
#[ORM\UniqueConstraint(name: 'uniq_team_date', columns: ['team_id', 'recorded_at'])]
class TeamDailySnapshot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Team::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Team $team;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $recordedAt;

    #[ORM\Column]
    private int $morale;

    #[ORM\Column]
    private int $reputation;

    #[ORM\Column]
    private int $chemistry;

    #[ORM\Column]
    private int $fanBase;

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

    public function getRecordedAt(): \DateTimeImmutable
    {
        return $this->recordedAt;
    }

    public function setRecordedAt(\DateTimeImmutable $recordedAt): static
    {
        $this->recordedAt = $recordedAt;

        return $this;
    }

    public function getMorale(): int
    {
        return $this->morale;
    }

    public function setMorale(int $morale): static
    {
        $this->morale = $morale;

        return $this;
    }

    public function getReputation(): int
    {
        return $this->reputation;
    }

    public function setReputation(int $reputation): static
    {
        $this->reputation = $reputation;

        return $this;
    }

    public function getChemistry(): int
    {
        return $this->chemistry;
    }

    public function setChemistry(int $chemistry): static
    {
        $this->chemistry = $chemistry;

        return $this;
    }

    public function getFanBase(): int
    {
        return $this->fanBase;
    }

    public function setFanBase(int $fanBase): static
    {
        $this->fanBase = $fanBase;

        return $this;
    }
}
