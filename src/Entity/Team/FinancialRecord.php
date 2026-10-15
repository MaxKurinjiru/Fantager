<?php

declare(strict_types=1);

namespace App\Entity\Team;

use App\Enum\FinancialRecordActor;
use App\Enum\FinancialRecordType;
use App\Repository\Team\FinancialRecordRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FinancialRecordRepository::class)]
#[ORM\Table(name: 'team_financial_record')]
class FinancialRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Team $team;

    #[ORM\Column(length: 30, enumType: FinancialRecordType::class)]
    private FinancialRecordType $type;

    #[ORM\Column(length: 15, enumType: FinancialRecordActor::class)]
    private FinancialRecordActor $actor;

    #[ORM\Column(options: ['default' => 0])]
    private int $goldChange = 0;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $context = [];

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

    public function getType(): FinancialRecordType
    {
        return $this->type;
    }

    public function setType(FinancialRecordType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getActor(): FinancialRecordActor
    {
        return $this->actor;
    }

    public function setActor(FinancialRecordActor $actor): static
    {
        $this->actor = $actor;

        return $this;
    }

    public function getGoldChange(): int
    {
        return $this->goldChange;
    }

    public function setGoldChange(int $goldChange): static
    {
        $this->goldChange = $goldChange;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getContext(): array
    {
        return $this->context;
    }

    /** @param array<string, mixed> $context */
    public function setContext(array $context): static
    {
        $this->context = $context;

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
