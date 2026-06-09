<?php

declare(strict_types=1);

namespace App\Repository\Hero;

use App\Entity\Hero\SchoolMastery;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SchoolMasteryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SchoolMastery::class);
    }
}
