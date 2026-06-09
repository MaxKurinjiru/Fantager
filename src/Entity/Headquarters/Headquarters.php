<?php

declare(strict_types=1);

namespace App\Entity\Headquarters;

use App\Entity\Team\Team;
use App\Repository\Headquarters\HeadquartersRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HeadquartersRepository::class)]
#[ORM\Table(name: 'headquarters')]
class Headquarters
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Team $team;

    #[ORM\Column(options: ['default' => 1])]
    private int $totalLevel = 1;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $raceOptimization = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $pendingRaceOptimization = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $hasPendingRaceOptimizationChange = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $raceOptimizationLockCycle = false;

    /** @var Collection<int, Facility> */
    #[ORM\OneToMany(targetEntity: Facility::class, mappedBy: 'headquarters', cascade: ['persist'])]
    private Collection $facilities;

    public function __construct()
    {
        $this->facilities = new ArrayCollection();
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

    public function getTotalLevel(): int
    {
        return $this->totalLevel;
    }

    public function setTotalLevel(int $totalLevel): static
    {
        $this->totalLevel = $totalLevel;

        return $this;
    }

    public function getRaceOptimization(): ?string
    {
        return $this->raceOptimization;
    }

    public function setRaceOptimization(?string $raceOptimization): static
    {
        $this->raceOptimization = $raceOptimization;

        return $this;
    }

    public function getPendingRaceOptimization(): ?string
    {
        return $this->pendingRaceOptimization;
    }

    public function setPendingRaceOptimization(?string $pendingRaceOptimization): static
    {
        $this->pendingRaceOptimization = $pendingRaceOptimization;

        return $this;
    }

    public function hasPendingRaceOptimizationChange(): bool
    {
        return $this->hasPendingRaceOptimizationChange;
    }

    public function setHasPendingRaceOptimizationChange(bool $hasPendingRaceOptimizationChange): static
    {
        $this->hasPendingRaceOptimizationChange = $hasPendingRaceOptimizationChange;

        return $this;
    }

    public function isRaceOptimizationLockCycle(): bool
    {
        return $this->raceOptimizationLockCycle;
    }

    public function setRaceOptimizationLockCycle(bool $raceOptimizationLockCycle): static
    {
        $this->raceOptimizationLockCycle = $raceOptimizationLockCycle;

        return $this;
    }

    /** @return Collection<int, Facility> */
    public function getFacilities(): Collection
    {
        return $this->facilities;
    }

    public function addFacility(Facility $facility): static
    {
        if (!$this->facilities->contains($facility)) {
            $this->facilities->add($facility);
            $facility->setHeadquarters($this);
        }

        return $this;
    }
}
