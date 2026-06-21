<?php

declare(strict_types=1);

namespace App\Entity\Marketplace;

use App\Entity\Team\Team;
use App\Repository\Marketplace\MarketplaceBidRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MarketplaceBidRepository::class)]
#[ORM\Table(name: 'marketplace_bid')]
class MarketplaceBid
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'bids')]
    #[ORM\JoinColumn(nullable: false)]
    private MarketplaceListing $listing;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Team $bidderTeam;

    #[ORM\Column]
    private int $bidAmount;

    #[ORM\Column]
    private \DateTimeImmutable $bidTime;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getListing(): MarketplaceListing
    {
        return $this->listing;
    }

    public function setListing(MarketplaceListing $listing): static
    {
        $this->listing = $listing;

        return $this;
    }

    public function getBidderTeam(): Team
    {
        return $this->bidderTeam;
    }

    public function setBidderTeam(Team $bidderTeam): static
    {
        $this->bidderTeam = $bidderTeam;

        return $this;
    }

    public function getBidAmount(): int
    {
        return $this->bidAmount;
    }

    public function setBidAmount(int $bidAmount): static
    {
        $this->bidAmount = $bidAmount;

        return $this;
    }

    public function getBidTime(): \DateTimeImmutable
    {
        return $this->bidTime;
    }

    public function setBidTime(\DateTimeImmutable $bidTime): static
    {
        $this->bidTime = $bidTime;

        return $this;
    }
}
