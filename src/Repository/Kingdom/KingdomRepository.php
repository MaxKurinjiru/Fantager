<?php

declare(strict_types=1);

namespace App\Repository\Kingdom;

use App\Entity\Kingdom\Kingdom;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Kingdom>
 */
class KingdomRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Kingdom::class);
    }
}
