<?php

declare(strict_types=1);

namespace App\Repository\League;

use App\Entity\League\LeagueTier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LeagueTier>
 */
class LeagueTierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LeagueTier::class);
    }
}
