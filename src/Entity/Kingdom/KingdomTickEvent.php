<?php

declare(strict_types=1);

namespace App\Entity\Kingdom;

use App\Enum\TickType;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'kingdom_tick_event')]
#[ORM\UniqueConstraint(name: 'uniq_tick_event_identity', columns: ['kingdom_id', 'tick_type', 'scheduled_at'])]
class KingdomTickEvent
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

    public function __construct(Kingdom $kingdom, TickType $tickType, \DateTimeImmutable $scheduledAt)
    {
        $this->kingdom = $kingdom;
        $this->tickType = $tickType;
        $this->scheduledAt = $scheduledAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKingdom(): Kingdom
    {
        return $this->kingdom;
    }

    public function getTickType(): TickType
    {
        return $this->tickType;
    }

    public function getScheduledAt(): \DateTimeImmutable
    {
        return $this->scheduledAt;
    }
}
