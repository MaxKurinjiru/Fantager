<?php

declare(strict_types=1);

namespace App\Repository\Marketplace;

use App\Entity\Marketplace\MarketplaceTransaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MarketplaceTransaction>
 */
class MarketplaceTransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MarketplaceTransaction::class);
    }
}
