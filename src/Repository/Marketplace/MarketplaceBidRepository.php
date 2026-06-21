<?php

declare(strict_types=1);

namespace App\Repository\Marketplace;

use App\Entity\Marketplace\MarketplaceBid;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MarketplaceBid>
 */
class MarketplaceBidRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MarketplaceBid::class);
    }
}
