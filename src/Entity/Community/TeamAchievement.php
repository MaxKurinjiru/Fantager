<?php

declare(strict_types=1);

namespace App\Entity\Community;

use App\Entity\Team\Team;
use App\Repository\Community\TeamAchievementRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TeamAchievementRepository::class)]
#[ORM\Table(name: 'team_achievement')]
#[ORM\UniqueConstraint(name: 'UNIQ_TEAM_ACHIEVEMENT', columns: ['team_id', 'achievement_id'])]
class TeamAchievement
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
    private Achievement $achievement;

    #[ORM\Column]
    private \DateTimeImmutable $unlockedAt;

    public function __construct()
    {
        $this->unlockedAt = new \DateTimeImmutable();
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

    public function getAchievement(): Achievement
    {
        return $this->achievement;
    }

    public function setAchievement(Achievement $achievement): static
    {
        $this->achievement = $achievement;

        return $this;
    }

    public function getUnlockedAt(): \DateTimeImmutable
    {
        return $this->unlockedAt;
    }
}
