<?php

declare(strict_types=1);

namespace App\Entity\Team;

use App\Enum\ChronicleEventType;
use App\Repository\Team\TeamChronicleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TeamChronicleRepository::class)]
#[ORM\Table(name: 'team_chronicle')]
#[ORM\Index(name: 'IDX_TEAM_TYPE', columns: ['team_id', 'type'])]
#[ORM\Index(name: 'IDX_CREATED_AT', columns: ['created_at'])]
class TeamChronicle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Team $team;

    #[ORM\Column(length: 30, enumType: ChronicleEventType::class)]
    private ChronicleEventType $type;

    #[ORM\Column(length: 255)]
    private string $subjectKey;

    #[ORM\Column(type: 'json')]
    private array $subjectParams = [];

    #[ORM\Column(type: 'json')]
    private array $data = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

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

    public function getType(): ChronicleEventType
    {
        return $this->type;
    }

    public function setType(ChronicleEventType $type): static
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

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getProcessedAt(): ?\DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function setProcessedAt(?\DateTimeImmutable $processedAt): static
    {
        $this->processedAt = $processedAt;

        return $this;
    }
}
