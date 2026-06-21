<?php

declare(strict_types=1);

namespace App\Repository\Marketplace;

use App\Entity\Marketplace\MarketplaceListing;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MarketplaceListing>
 */
class MarketplaceListingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MarketplaceListing::class);
    }
}
