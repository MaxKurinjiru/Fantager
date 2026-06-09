<?php

declare(strict_types=1);

namespace App\Entity\Graveyard;

use App\Entity\Team\Team;
use App\Enum\Race;
use App\Repository\Graveyard\GraveyardRecordRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GraveyardRecordRepository::class)]
#[ORM\Table(name: 'graveyard_record')]
class GraveyardRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Team $team;

    #[ORM\Column(length: 100)]
    private string $heroName;

    #[ORM\Column(length: 10, enumType: Race::class)]
    private Race $race;

    #[ORM\Column]
    private int $finalLevel;

    #[ORM\Column]
    private int $ageAtDeath;

    #[ORM\Column(length: 100)]
    private string $causeOfDeath;

    #[ORM\Column]
    private int $totalBattles;

    #[ORM\Column]
    private int $victories;

    #[ORM\Column(type: 'json')]
    private array $finalStats = [];

    #[ORM\Column(type: 'json')]
    private array $achievements = [];

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $dateOfDeath;

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

    public function getHeroName(): string
    {
        return $this->heroName;
    }

    public function setHeroName(string $heroName): static
    {
        $this->heroName = $heroName;

        return $this;
    }

    public function getRace(): Race
    {
        return $this->race;
    }

    public function setRace(Race $race): static
    {
        $this->race = $race;

        return $this;
    }

    public function getFinalLevel(): int
    {
        return $this->finalLevel;
    }

    public function setFinalLevel(int $finalLevel): static
    {
        $this->finalLevel = $finalLevel;

        return $this;
    }

    public function getAgeAtDeath(): int
    {
        return $this->ageAtDeath;
    }

    public function setAgeAtDeath(int $ageAtDeath): static
    {
        $this->ageAtDeath = $ageAtDeath;

        return $this;
    }

    public function getCauseOfDeath(): string
    {
        return $this->causeOfDeath;
    }

    public function setCauseOfDeath(string $causeOfDeath): static
    {
        $this->causeOfDeath = $causeOfDeath;

        return $this;
    }

    public function getTotalBattles(): int
    {
        return $this->totalBattles;
    }

    public function setTotalBattles(int $totalBattles): static
    {
        $this->totalBattles = $totalBattles;

        return $this;
    }

    public function getVictories(): int
    {
        return $this->victories;
    }

    public function setVictories(int $victories): static
    {
        $this->victories = $victories;

        return $this;
    }

    public function getFinalStats(): array
    {
        return $this->finalStats;
    }

    public function setFinalStats(array $finalStats): static
    {
        $this->finalStats = $finalStats;

        return $this;
    }

    public function getAchievements(): array
    {
        return $this->achievements;
    }

    public function setAchievements(array $achievements): static
    {
        $this->achievements = $achievements;

        return $this;
    }

    public function getDateOfDeath(): \DateTimeImmutable
    {
        return $this->dateOfDeath;
    }

    public function setDateOfDeath(\DateTimeImmutable $dateOfDeath): static
    {
        $this->dateOfDeath = $dateOfDeath;

        return $this;
    }
}
