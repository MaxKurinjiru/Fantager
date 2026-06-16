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

    #[ORM\ManyToOne(targetEntity: Facility::class)]
    #[ORM\JoinColumn(name: 'upgrading_facility_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Facility $upgradingFacility = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $upgradeCompletedAt = null;

    #[ORM\Column(length: 10, nullable: true, enumType: \App\Enum\FacilityOperation::class)]
    private ?\App\Enum\FacilityOperation $facilityOperation = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $facilityDowngradeLockCycle = false;

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

    /** Sum of all facility levels — source of truth for HQ total level in mechanics. */
    public function getComputedTotalLevel(): int
    {
        $total = 0;
        foreach ($this->facilities as $facility) {
            $total += $facility->getLevel();
        }

        return $total;
    }

    public function syncTotalLevel(): static
    {
        $this->totalLevel = $this->getComputedTotalLevel();

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

    public function getUpgradingFacility(): ?Facility
    {
        return $this->upgradingFacility;
    }

    public function setUpgradingFacility(?Facility $upgradingFacility): static
    {
        $this->upgradingFacility = $upgradingFacility;

        return $this;
    }

    public function getUpgradeCompletedAt(): ?\DateTimeImmutable
    {
        return $this->upgradeCompletedAt;
    }

    public function setUpgradeCompletedAt(?\DateTimeImmutable $upgradeCompletedAt): static
    {
        $this->upgradeCompletedAt = $upgradeCompletedAt;

        return $this;
    }

    public function getFacilityOperation(): ?\App\Enum\FacilityOperation
    {
        return $this->facilityOperation;
    }

    public function setFacilityOperation(?\App\Enum\FacilityOperation $facilityOperation): static
    {
        $this->facilityOperation = $facilityOperation;

        return $this;
    }

    public function isFacilityDowngradeLockCycle(): bool
    {
        return $this->facilityDowngradeLockCycle;
    }

    public function setFacilityDowngradeLockCycle(bool $facilityDowngradeLockCycle): static
    {
        $this->facilityDowngradeLockCycle = $facilityDowngradeLockCycle;

        return $this;
    }

    public function hasFacilityChangeInProgress(): bool
    {
        return null !== $this->upgradingFacility;
    }
}
