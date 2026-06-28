<?php

declare(strict_types=1);

namespace App\Repository\Hero;

use App\Entity\Hero\WeaponMastery;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WeaponMastery>
 */
class WeaponMasteryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WeaponMastery::class);
    }
}
