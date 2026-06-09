<?php

declare(strict_types=1);

namespace App\Repository\Crafting;

use App\Entity\Crafting\CraftingQueue;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CraftingQueueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CraftingQueue::class);
    }
}
