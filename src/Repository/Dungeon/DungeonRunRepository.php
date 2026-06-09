<?php

declare(strict_types=1);

namespace App\Repository\Dungeon;

use App\Entity\Dungeon\DungeonRun;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DungeonRunRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DungeonRun::class);
    }
}
