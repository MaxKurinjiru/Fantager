<?php

declare(strict_types=1);

namespace App\Repository\Graveyard;

use App\Entity\Graveyard\GraveyardRecord;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class GraveyardRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GraveyardRecord::class);
    }
}
