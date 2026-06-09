<?php

declare(strict_types=1);

namespace App\Entity\Quest;

use App\Entity\Team\Team;
use App\Enum\QuestProgressStatus;
use App\Repository\Quest\PlayerQuestProgressRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlayerQuestProgressRepository::class)]
#[ORM\Table(name: 'quest_player_progress')]
#[ORM\UniqueConstraint(name: 'UNIQ_TEAM_QUEST', columns: ['team_id', 'quest_id'])]
class PlayerQuestProgress
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Team $team;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Quest $quest;

    #[ORM\Column(length: 15, enumType: QuestProgressStatus::class)]
    private QuestProgressStatus $status = QuestProgressStatus::InProgress;

    #[ORM\Column(options: ['default' => 0])]
    private int $progress = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

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

    public function getQuest(): Quest
    {
        return $this->quest;
    }

    public function setQuest(Quest $quest): static
    {
        $this->quest = $quest;

        return $this;
    }

    public function getStatus(): QuestProgressStatus
    {
        return $this->status;
    }

    public function setStatus(QuestProgressStatus $status): static
    {
        $this->status = $status;

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

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): static
    {
        $this->completedAt = $completedAt;

        return $this;
    }
}
