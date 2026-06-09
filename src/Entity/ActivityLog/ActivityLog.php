<?php

declare(strict_types=1);

namespace App\Entity\ActivityLog;

use App\Entity\Team\Team;
use App\Enum\ActivityLogType;
use App\Repository\ActivityLog\ActivityLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActivityLogRepository::class)]
#[ORM\Table(name: 'activity_log')]
#[ORM\Index(name: 'IDX_TEAM_TYPE', columns: ['team_id', 'type'])]
#[ORM\Index(name: 'IDX_CREATED_AT', columns: ['created_at'])]
class ActivityLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Team $team;

    #[ORM\Column(length: 30, enumType: ActivityLogType::class)]
    private ActivityLogType $type;

    #[ORM\Column(length: 255)]
    private string $subjectKey;

    #[ORM\Column(type: 'json')]
    private array $subjectParams = [];

    #[ORM\Column(type: 'json')]
    private array $data = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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

    public function getType(): ActivityLogType
    {
        return $this->type;
    }

    public function setType(ActivityLogType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getSubjectKey(): string
    {
        return $this->subjectKey;
    }

    public function setSubjectKey(string $subjectKey): static
    {
        $this->subjectKey = $subjectKey;

        return $this;
    }

    public function getSubjectParams(): array
    {
        return $this->subjectParams;
    }

    public function setSubjectParams(array $subjectParams): static
    {
        $this->subjectParams = $subjectParams;

        return $this;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
