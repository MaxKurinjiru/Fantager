<?php

declare(strict_types=1);

namespace App\Entity\League;

use App\Repository\League\LeagueGroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LeagueGroupRepository::class)]
#[ORM\Table(name: 'league_group')]
class LeagueGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'groups')]
    #[ORM\JoinColumn(nullable: false)]
    private LeagueTier $tier;

    #[ORM\Column(length: 50)]
    private string $groupName;

    /** @var Collection<int, LeagueStanding> */
    #[ORM\OneToMany(targetEntity: LeagueStanding::class, mappedBy: 'group', cascade: ['persist'])]
    private Collection $standings;

    /** @var Collection<int, LeagueFixture> */
    #[ORM\OneToMany(targetEntity: LeagueFixture::class, mappedBy: 'group', cascade: ['persist'])]
    private Collection $fixtures;

    public function __construct()
    {
        $this->standings = new ArrayCollection();
        $this->fixtures = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTier(): LeagueTier
    {
        return $this->tier;
    }

    public function setTier(LeagueTier $tier): static
    {
        $this->tier = $tier;

        return $this;
    }

    public function getGroupName(): string
    {
        return $this->groupName;
    }

    public function setGroupName(string $groupName): static
    {
        $this->groupName = $groupName;

        return $this;
    }

    /** @return Collection<int, LeagueStanding> */
    public function getStandings(): Collection
    {
        return $this->standings;
    }

    /** @return Collection<int, LeagueFixture> */
    public function getFixtures(): Collection
    {
        return $this->fixtures;
    }
}
