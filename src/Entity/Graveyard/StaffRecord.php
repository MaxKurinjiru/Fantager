<?php

declare(strict_types=1);

namespace App\Entity\Graveyard;

use App\Entity\Team\Team;
use App\Enum\Race;
use App\Enum\StaffDepartureCause;
use App\Repository\Graveyard\StaffRecordRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StaffRecordRepository::class)]
#[ORM\Table(name: 'staff_record')]
class StaffRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Team $team;

    #[ORM\Column(length: 100)]
    private string $name;

    #[ORM\Column(length: 10, enumType: Race::class)]
    private Race $race;

    #[ORM\Column]
    private int $age;

    #[ORM\Column(length: 20, enumType: StaffDepartureCause::class)]
    private StaffDepartureCause $cause;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $trainingType = null;

    #[ORM\Column(type: 'json')]
    private array $finalStats = [];

    #[ORM\Column]
    private int $traineesCount = 0;

    #[ORM\Column(nullable: true)]
    private ?int $originalTrainerId = null;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $dateOfDeparture;

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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    public function getAge(): int
    {
        return $this->age;
    }

    public function setAge(int $age): static
    {
        $this->age = $age;

        return $this;
    }

    public function getCause(): StaffDepartureCause
    {
        return $this->cause;
    }

    public function setCause(StaffDepartureCause $cause): static
    {
        $this->cause = $cause;

        return $this;
    }

    public function getTrainingType(): ?string
    {
        return $this->trainingType;
    }

    public function setTrainingType(?string $trainingType): static
    {
        $this->trainingType = $trainingType;

        return $this;
    }

    /** @return array<string, int> */
    public function getFinalStats(): array
    {
        return $this->finalStats;
    }

    /** @param array<string, int> $finalStats */
    public function setFinalStats(array $finalStats): static
    {
        $this->finalStats = $finalStats;

        return $this;
    }

    public function getTraineesCount(): int
    {
        return $this->traineesCount;
    }

    public function setTraineesCount(int $traineesCount): static
    {
        $this->traineesCount = $traineesCount;

        return $this;
    }

    public function getOriginalTrainerId(): ?int
    {
        return $this->originalTrainerId;
    }

    public function setOriginalTrainerId(?int $originalTrainerId): static
    {
        $this->originalTrainerId = $originalTrainerId;

        return $this;
    }

    public function getDateOfDeparture(): \DateTimeImmutable
    {
        return $this->dateOfDeparture;
    }

    public function setDateOfDeparture(\DateTimeImmutable $dateOfDeparture): static
    {
        $this->dateOfDeparture = $dateOfDeparture;

        return $this;
    }
}
