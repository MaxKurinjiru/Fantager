<?php

declare(strict_types=1);

namespace App\Entity\Combat;

use App\Entity\Formation\Formation;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;
use App\Enum\BattleResult;
use App\Enum\MatchType;
use App\Repository\Combat\BattleRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BattleRepository::class)]
#[ORM\Table(name: 'combat_battle')]
class Battle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Kingdom $kingdom;

    #[ORM\Column(length: 15, enumType: MatchType::class)]
    private MatchType $matchType;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Team $teamA;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Team $teamB;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Formation $formationA = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Formation $formationB = null;

    #[ORM\Column(length: 10, enumType: BattleResult::class, nullable: true)]
    private ?BattleResult $result = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $scoreA = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $scoreB = 0;

    #[ORM\Column(type: 'json')]
    private array $combatLog = [];

    #[ORM\Column(options: ['default' => 0])]
    private int $xpAwarded = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

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

    public function getMatchType(): MatchType
    {
        return $this->matchType;
    }

    public function setMatchType(MatchType $matchType): static
    {
        $this->matchType = $matchType;

        return $this;
    }

    public function getTeamA(): Team
    {
        return $this->teamA;
    }

    public function setTeamA(Team $teamA): static
    {
        $this->teamA = $teamA;

        return $this;
    }

    public function getTeamB(): Team
    {
        return $this->teamB;
    }

    public function setTeamB(Team $teamB): static
    {
        $this->teamB = $teamB;

        return $this;
    }

    public function getFormationA(): ?Formation
    {
        return $this->formationA;
    }

    public function setFormationA(?Formation $formationA): static
    {
        $this->formationA = $formationA;

        return $this;
    }

    public function getFormationB(): ?Formation
    {
        return $this->formationB;
    }

    public function setFormationB(?Formation $formationB): static
    {
        $this->formationB = $formationB;

        return $this;
    }

    public function getResult(): ?BattleResult
    {
        return $this->result;
    }

    public function setResult(?BattleResult $result): static
    {
        $this->result = $result;

        return $this;
    }

    public function getScoreA(): int
    {
        return $this->scoreA;
    }

    public function setScoreA(int $scoreA): static
    {
        $this->scoreA = $scoreA;

        return $this;
    }

    public function getScoreB(): int
    {
        return $this->scoreB;
    }

    public function setScoreB(int $scoreB): static
    {
        $this->scoreB = $scoreB;

        return $this;
    }

    public function getCombatLog(): array
    {
        return $this->combatLog;
    }

    public function setCombatLog(array $combatLog): static
    {
        $this->combatLog = $combatLog;

        return $this;
    }

    public function getXpAwarded(): int
    {
        return $this->xpAwarded;
    }

    public function setXpAwarded(int $xpAwarded): static
    {
        $this->xpAwarded = $xpAwarded;

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
