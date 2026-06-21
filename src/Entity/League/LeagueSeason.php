<?php

declare(strict_types=1);

namespace App\Entity\League;

use App\Entity\Kingdom\Kingdom;
use App\Enum\LeagueSeasonStatus;
use App\Repository\League\LeagueSeasonRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LeagueSeasonRepository::class)]
#[ORM\Table(name: 'league_season')]
class LeagueSeason
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Kingdom $kingdom;

    #[ORM\Column]
    private int $seasonNumber;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $startDate;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $endDate;

    #[ORM\Column(length: 20, enumType: LeagueSeasonStatus::class)]
    private LeagueSeasonStatus $status = LeagueSeasonStatus::Scheduled;

    /** @var Collection<int, LeagueTier> */
    #[ORM\OneToMany(targetEntity: LeagueTier::class, mappedBy: 'season', cascade: ['persist'])]
    private Collection $tiers;

    public function __construct()
    {
        $this->tiers = new ArrayCollection();
    }

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

    public function getSeasonNumber(): int
    {
        return $this->seasonNumber;
    }

    public function setSeasonNumber(int $seasonNumber): static
    {
        $this->seasonNumber = $seasonNumber;

        return $this;
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): \DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getStatus(): LeagueSeasonStatus
    {
        return $this->status;
    }

    public function setStatus(LeagueSeasonStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    /** @return Collection<int, LeagueTier> */
    public function getTiers(): Collection
    {
        return $this->tiers;
    }

    public function addTier(LeagueTier $tier): static
    {
        if (!$this->tiers->contains($tier)) {
            $this->tiers->add($tier);
            $tier->setSeason($this);
        }

        return $this;
    }
}
