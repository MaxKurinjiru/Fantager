<?php

declare(strict_types=1);

namespace App\Repository\Quest;

use App\Entity\Quest\PlayerQuestProgress;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class PlayerQuestProgressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlayerQuestProgress::class);
    }
}
