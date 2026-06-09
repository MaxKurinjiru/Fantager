<?php

declare(strict_types=1);

namespace App\Entity\Event;

use App\Entity\Team\Team;
use App\Repository\Event\EventParticipationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EventParticipationRepository::class)]
#[ORM\Table(name: 'event_participation')]
#[ORM\UniqueConstraint(name: 'UNIQ_EVENT_TEAM', columns: ['event_id', 'team_id'])]
class EventParticipation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Event $event;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Team $team;

    #[ORM\Column(options: ['default' => 0])]
    private int $progress = 0;

    #[ORM\Column(options: ['default' => false])]
    private bool $rewardsClaimed = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEvent(): Event
    {
        return $this->event;
    }

    public function setEvent(Event $event): static
    {
        $this->event = $event;

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

    public function getProgress(): int
    {
        return $this->progress;
    }

    public function setProgress(int $progress): static
    {
        $this->progress = $progress;

        return $this;
    }

    public function isRewardsClaimed(): bool
    {
        return $this->rewardsClaimed;
    }

    public function setRewardsClaimed(bool $rewardsClaimed): static
    {
        $this->rewardsClaimed = $rewardsClaimed;

        return $this;
    }
}
