<?php

declare(strict_types=1);

namespace App\Entity\Marketplace;

use App\Entity\Team\Team;
use App\Enum\TransactionType;
use App\Repository\Marketplace\TransactionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ORM\Table(name: 'marketplace_transaction')]
class Transaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Team $buyerTeam;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Team $sellerTeam;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private MarketplaceListing $listing;

    #[ORM\Column]
    private int $amount;

    #[ORM\Column]
    private int $feeAmount;

    #[ORM\Column(length: 20, enumType: TransactionType::class)]
    private TransactionType $type;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBuyerTeam(): Team
    {
        return $this->buyerTeam;
    }

    public function setBuyerTeam(Team $buyerTeam): static
    {
        $this->buyerTeam = $buyerTeam;

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

    public function getListing(): MarketplaceListing
    {
        return $this->listing;
    }

    public function setListing(MarketplaceListing $listing): static
    {
        $this->listing = $listing;

        return $this;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getFeeAmount(): int
    {
        return $this->feeAmount;
    }

    public function setFeeAmount(int $feeAmount): static
    {
        $this->feeAmount = $feeAmount;

        return $this;
    }

    public function getType(): TransactionType
    {
        return $this->type;
    }

    public function setType(TransactionType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
