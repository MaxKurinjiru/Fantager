<?php

declare(strict_types=1);

namespace App\Entity\Kingdom;

use App\Entity\FriendlyMatch\FriendlyMatch;
use App\Entity\League\LeagueFixture;
use App\Entity\Team\Team;
use App\Enum\TickType;
use App\Repository\Kingdom\KingdomTickLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: KingdomTickLogRepository::class)]
#[ORM\Table(name: 'kingdom_tick_log')]
#[ORM\UniqueConstraint(name: 'uniq_tick_event_team', columns: ['event_id', 'team_id'])]
#[ORM\UniqueConstraint(name: 'uniq_tick_event_fixture', columns: ['event_id', 'fixture_id'])]
#[ORM\UniqueConstraint(name: 'uniq_tick_event_friendly', columns: ['event_id', 'friendly_match_id'])]
#[ORM\UniqueConstraint(name: 'uniq_tick_event_kingdom', columns: ['event_id', 'kingdom_scope'])]
#[ORM\UniqueConstraint(name: 'uniq_tick_event_market_batch', columns: ['event_id', 'marketplace_batch'])]
class KingdomTickLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    private Kingdom $kingdom;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Team $team = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?LeagueFixture $fixture = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'friendly_match_id', nullable: true, onDelete: 'CASCADE')]
    private ?FriendlyMatch $friendlyMatch = null;

    private TickType $tickType;

    private \DateTimeImmutable $scheduledAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private KingdomTickEvent $event;

    #[ORM\Column(name: 'kingdom_scope', nullable: true)]
    private ?bool $kingdomScope = true;

    #[ORM\Column(name: 'marketplace_batch', nullable: true)]
    private ?int $marketplaceBatch = null;

    #[ORM\Column(name: 'marketplace_max_listing_id', nullable: true)]
    private ?int $marketplaceMaxListingId = null;

    #[ORM\Column(length: 15)]
    private string $status = 'pending';

    #[ORM\Column(name: 'error_message', type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(name: 'executed_at')]
    private \DateTimeImmutable $executedAt;

    #[ORM\Column(name: 'retry_count', type: 'integer', options: ['default' => 0])]
    private int $retryCount = 0;

    #[ORM\Column(name: 'combat_recovery_count', options: ['default' => 0])]
    private int $combatRecoveryCount = 0;

    #[ORM\Column(name: 'combat_recovery_round', nullable: true)]
    private ?int $combatRecoveryRound = null;

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
        return isset($this->event) ? $this->event->getKingdom() : $this->kingdom;
    }

    public function setKingdom(Kingdom $kingdom): static
    {
        if (isset($this->event) && $this->event->getKingdom() !== $kingdom) {
            throw new \LogicException('A persisted tick phase cannot change its event identity.');
        }
        $this->kingdom = $kingdom;

        return $this;
    }

    public function getTickType(): TickType
    {
        return isset($this->event) ? $this->event->getTickType() : $this->tickType;
    }

    public function setTickType(TickType $tickType): static
    {
        if (isset($this->event) && $this->event->getTickType() !== $tickType) {
            throw new \LogicException('A persisted tick phase cannot change its event identity.');
        }
        $this->tickType = $tickType;

        return $this;
    }

    public function getScheduledAt(): \DateTimeImmutable
    {
        return isset($this->event) ? $this->event->getScheduledAt() : $this->scheduledAt;
    }

    public function setScheduledAt(\DateTimeImmutable $scheduledAt): static
    {
        if (isset($this->event) && $this->event->getScheduledAt() != $scheduledAt) {
            throw new \LogicException('A persisted tick phase cannot change its event identity.');
        }
        $this->scheduledAt = $scheduledAt;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCombatRecoveryCount(): int
    {
        return $this->combatRecoveryCount;
    }

    public function getCombatRecoveryRound(): ?int
    {
        return $this->combatRecoveryRound;
    }

    public function recordCombatRecovery(int $round): void
    {
        $this->combatRecoveryCount = $this->combatRecoveryRound === $round ? $this->combatRecoveryCount + 1 : 1;
        $this->combatRecoveryRound = $round;
    }

    public function resetCombatRecovery(): void
    {
        $this->combatRecoveryCount = 0;
        $this->combatRecoveryRound = null;
    }

    public function getMarketplaceBatch(): ?int
    {
        return $this->marketplaceBatch;
    }

    public function getMarketplaceMaxListingId(): ?int
    {
        return $this->marketplaceMaxListingId;
    }

    public function setMarketplaceBatch(int $batch, int $maximumListingId): static
    {
        if ($batch < 1 || $maximumListingId < 1 || null !== $this->team || null !== $this->fixture || null !== $this->friendlyMatch) {
            throw new \InvalidArgumentException('Invalid marketplace batch scope.');
        }
        $this->marketplaceBatch = $batch;
        $this->marketplaceMaxListingId = $maximumListingId;
        $this->updateKingdomScope();

        return $this;
    }

    public function getEvent(): ?KingdomTickEvent
    {
        return isset($this->event) ? $this->event : null;
    }

    public function setEvent(KingdomTickEvent $event): static
    {
        if (isset($this->event)) {
            if ($this->event !== $event) {
                throw new \LogicException('A persisted tick phase cannot change its event identity.');
            }

            return $this;
        }
        if ($this->kingdom !== $event->getKingdom() || $this->tickType !== $event->getTickType() || $this->scheduledAt != $event->getScheduledAt()) {
            throw new \InvalidArgumentException('Tick phase and event identities must match.');
        }
        $this->event = $event;

        return $this;
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

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    public function setTeam(?Team $team): static
    {
        if (null !== $team && (null !== $this->fixture || null !== $this->friendlyMatch || null !== $this->marketplaceBatch)) {
            throw new \InvalidArgumentException('A tick phase must have only one scope.');
        }
        $this->team = $team;
        $this->updateKingdomScope();

        return $this;
    }

    public function getFixture(): ?LeagueFixture
    {
        return $this->fixture;
    }

    public function setFixture(?LeagueFixture $fixture): static
    {
        if (null !== $fixture && (null !== $this->team || null !== $this->friendlyMatch || null !== $this->marketplaceBatch)) {
            throw new \InvalidArgumentException('A tick phase must have only one scope.');
        }
        $this->fixture = $fixture;
        $this->updateKingdomScope();

        return $this;
    }

    public function getFriendlyMatch(): ?FriendlyMatch
    {
        return $this->friendlyMatch;
    }

    public function setFriendlyMatch(?FriendlyMatch $friendlyMatch): static
    {
        if (null !== $friendlyMatch && (null !== $this->team || null !== $this->fixture || null !== $this->marketplaceBatch)) {
            throw new \InvalidArgumentException('A tick phase must have only one scope.');
        }
        $this->friendlyMatch = $friendlyMatch;
        $this->updateKingdomScope();

        return $this;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    public function setRetryCount(int $retryCount): static
    {
        $this->retryCount = $retryCount;

        return $this;
    }

    private function updateKingdomScope(): void
    {
        $this->kingdomScope = null === $this->team && null === $this->fixture && null === $this->friendlyMatch && null === $this->marketplaceBatch ? true : null;
    }
}
