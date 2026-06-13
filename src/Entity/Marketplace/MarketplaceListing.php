<?php

declare(strict_types=1);

namespace App\Entity\Marketplace;

use App\Entity\Hero\Hero;
use App\Entity\Item\Item;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;
use App\Entity\Training\Trainer;
use App\Enum\ListingMode;
use App\Enum\ListingStatus;
use App\Enum\ListingType;
use App\Repository\Marketplace\MarketplaceListingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MarketplaceListingRepository::class)]
#[ORM\Table(name: 'marketplace_listing')]
class MarketplaceListing
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Kingdom $kingdom;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Team $sellerTeam;

    #[ORM\Column(length: 20, enumType: ListingType::class)]
    private ListingType $listingType;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Hero $hero = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Item $item = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Trainer $trainer = null;

    #[ORM\Column]
    private int $priceGold;

    #[ORM\Column(nullable: true)]
    private ?int $buyoutPriceGold = null;

    #[ORM\Column(length: 20, enumType: ListingMode::class)]
    private ListingMode $listingMode;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(length: 20, enumType: ListingStatus::class)]
    private ListingStatus $status = ListingStatus::Active;

    /** @var Collection<int, MarketplaceBid> */
    #[ORM\OneToMany(targetEntity: MarketplaceBid::class, mappedBy: 'listing', cascade: ['persist'])]
    private Collection $bids;

    public function __construct()
    {
        $this->bids = new ArrayCollection();
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

    public function getSellerTeam(): Team
    {
        return $this->sellerTeam;
    }

    public function setSellerTeam(Team $sellerTeam): static
    {
        $this->sellerTeam = $sellerTeam;

        return $this;
    }

    public function getListingType(): ListingType
    {
        return $this->listingType;
    }

    public function setListingType(ListingType $listingType): static
    {
        $this->listingType = $listingType;

        return $this;
    }

    public function getHero(): ?Hero
    {
        return $this->hero;
    }

    public function setHero(?Hero $hero): static
    {
        $this->hero = $hero;

        return $this;
    }

    public function getItem(): ?Item
    {
        return $this->item;
    }

    public function setItem(?Item $item): static
    {
        $this->item = $item;

        return $this;
    }

    public function getTrainer(): ?Trainer
    {
        return $this->trainer;
    }

    public function setTrainer(?Trainer $trainer): static
    {
        $this->trainer = $trainer;

        return $this;
    }

    public function getPriceGold(): int
    {
        return $this->priceGold;
    }

    public function setPriceGold(int $priceGold): static
    {
        $this->priceGold = $priceGold;

        return $this;
    }

    public function getListingMode(): ListingMode
    {
        return $this->listingMode;
    }

    public function setListingMode(ListingMode $listingMode): static
    {
        $this->listingMode = $listingMode;

        return $this;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getStatus(): ListingStatus
    {
        return $this->status;
    }

    public function setStatus(ListingStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    /** @return Collection<int, MarketplaceBid> */
    public function getBids(): Collection
    {
        return $this->bids;
    }

    public function getBuyoutPriceGold(): ?int
    {
        return $this->buyoutPriceGold;
    }

    public function setBuyoutPriceGold(?int $buyoutPriceGold): static
    {
        $this->buyoutPriceGold = $buyoutPriceGold;

        return $this;
    }
}
