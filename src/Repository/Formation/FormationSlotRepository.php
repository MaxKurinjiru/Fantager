<?php

declare(strict_types=1);

namespace App\Repository\Formation;

use App\Entity\Formation\FormationSlot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FormationSlotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FormationSlot::class);
    }
}
