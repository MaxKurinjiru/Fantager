<?php

declare(strict_types=1);

namespace App\Entity\Kingdom;

use App\Enum\TickType;
use App\Repository\Kingdom\KingdomTickLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: KingdomTickLogRepository::class)]
#[ORM\Table(name: 'kingdom_tick_log')]
#[ORM\UniqueConstraint(name: 'uniq_kingdom_tick_type_scheduled', columns: ['kingdom_id', 'tick_type', 'scheduled_at'])]
class KingdomTickLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Kingdom $kingdom;

    #[ORM\Column(name: 'tick_type', length: 30, enumType: TickType::class)]
    private TickType $tickType;

    #[ORM\Column(name: 'scheduled_at')]
    private \DateTimeImmutable $scheduledAt;

    #[ORM\Column(length: 15)]
    private string $status = 'processing';

    #[ORM\Column(name: 'error_message', type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(name: 'executed_at')]
    private \DateTimeImmutable $executedAt;

    public function __construct()
    {
        $this->executedAt = new \DateTimeImmutable('now');
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

    public function getTickType(): TickType
    {
        return $this->tickType;
    }

    public function setTickType(TickType $tickType): static
    {
        $this->tickType = $tickType;

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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    public function getExecutedAt(): \DateTimeImmutable
    {
        return $this->executedAt;
    }

    public function setExecutedAt(\DateTimeImmutable $executedAt): static
    {
        $this->executedAt = $executedAt;

        return $this;
    }
}
