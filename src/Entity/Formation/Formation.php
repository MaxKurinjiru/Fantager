<?php

declare(strict_types=1);

namespace App\Entity\Formation;

use App\Entity\League\LeagueFixture;
use App\Entity\Team\Team;
use App\Enum\FormationApproach;
use App\Repository\Formation\FormationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FormationRepository::class)]
#[ORM\Table(name: 'formation')]
class Formation
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

    #[ORM\Column(options: ['default' => false])]
    private bool $isDefault = false;

    #[ORM\Column(length: 15, enumType: FormationApproach::class)]
    private FormationApproach $approach = FormationApproach::Balanced;

    /**
     * Formation-wide tactics template: row movement defaults and L1 targeting.
     * Slots inherit unless they store inherit_movement / inherit_targeting = false.
     *
     * @var array<string, mixed>
     */
    #[ORM\Column(type: 'json')]
    private array $tacticsDefaults = [];

    #[ORM\Column(options: ['default' => false])]
    private bool $isTemporary = false;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?LeagueFixture $sourceFixture = null;

    /** @var Collection<int, FormationSlot> */
    #[ORM\OneToMany(targetEntity: FormationSlot::class, mappedBy: 'formation', cascade: ['persist'])]
    private Collection $slots;

    public function __construct()
    {
        $this->slots = new ArrayCollection();
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;

        return $this;
    }

    public function getApproach(): FormationApproach
    {
        return $this->approach;
    }

    public function setApproach(FormationApproach $approach): static
    {
        $this->approach = $approach;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getTacticsDefaults(): array
    {
        return $this->tacticsDefaults;
    }

    /** @param array<string, mixed> $tacticsDefaults */
    public function setTacticsDefaults(array $tacticsDefaults): static
    {
        $this->tacticsDefaults = $tacticsDefaults;

        return $this;
    }

    public function isTemporary(): bool
    {
        return $this->isTemporary;
    }

    public function setIsTemporary(bool $isTemporary): static
    {
        $this->isTemporary = $isTemporary;

        return $this;
    }

    public function getSourceFixture(): ?LeagueFixture
    {
        return $this->sourceFixture;
    }

    public function setSourceFixture(?LeagueFixture $sourceFixture): static
    {
        $this->sourceFixture = $sourceFixture;

        return $this;
    }

    /** @return Collection<int, FormationSlot> */
    public function getSlots(): Collection
    {
        return $this->slots;
    }

    public function addSlot(FormationSlot $slot): static
    {
        if (!$this->slots->contains($slot)) {
            $this->slots->add($slot);
            $slot->setFormation($this);
        }

        return $this;
    }
}
