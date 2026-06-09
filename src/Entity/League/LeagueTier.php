<?php

declare(strict_types=1);

namespace App\Entity\League;

use App\Repository\League\LeagueTierRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LeagueTierRepository::class)]
#[ORM\Table(name: 'league_tier')]
class LeagueTier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'tiers')]
    #[ORM\JoinColumn(nullable: false)]
    private LeagueSeason $season;

    #[ORM\Column(length: 50)]
    private string $tierName;

    #[ORM\Column]
    private int $promotionSlots;

    #[ORM\Column]
    private int $relegationSlots;

    #[ORM\Column(type: 'json')]
    private array $rewards = [];

    /** @var Collection<int, LeagueGroup> */
    #[ORM\OneToMany(targetEntity: LeagueGroup::class, mappedBy: 'tier', cascade: ['persist'])]
    private Collection $groups;

    public function __construct()
    {
        $this->groups = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSeason(): LeagueSeason
    {
        return $this->season;
    }

    public function setSeason(LeagueSeason $season): static
    {
        $this->season = $season;

        return $this;
    }

    public function getTierName(): string
    {
        return $this->tierName;
    }

    public function setTierName(string $tierName): static
    {
        $this->tierName = $tierName;

        return $this;
    }

    public function getPromotionSlots(): int
    {
        return $this->promotionSlots;
    }

    public function setPromotionSlots(int $promotionSlots): static
    {
        $this->promotionSlots = $promotionSlots;

        return $this;
    }

    public function getRelegationSlots(): int
    {
        return $this->relegationSlots;
    }

    public function setRelegationSlots(int $relegationSlots): static
    {
        $this->relegationSlots = $relegationSlots;

        return $this;
    }

    public function getRewards(): array
    {
        return $this->rewards;
    }

    public function setRewards(array $rewards): static
    {
        $this->rewards = $rewards;

        return $this;
    }

    /** @return Collection<int, LeagueGroup> */
    public function getGroups(): Collection
    {
        return $this->groups;
    }

    public function addGroup(LeagueGroup $group): static
    {
        if (!$this->groups->contains($group)) {
            $this->groups->add($group);
            $group->setTier($this);
        }

        return $this;
    }
}
