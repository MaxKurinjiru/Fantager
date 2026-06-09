<?php

declare(strict_types=1);

namespace App\Repository\Summoning;

use App\Entity\Summoning\SummonHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SummonHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SummonHistory::class);
    }
}
