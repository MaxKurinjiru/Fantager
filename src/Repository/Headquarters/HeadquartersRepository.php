<?php

declare(strict_types=1);

namespace App\Repository\Headquarters;

use App\Entity\Headquarters\Headquarters;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class HeadquartersRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Headquarters::class);
    }

    /**
     * @return list<Headquarters>
     */
    public function findByKingdom(\App\Entity\Kingdom\Kingdom $kingdom): array
    {
        return $this->createQueryBuilder('hq')
            ->join('hq.team', 't')
            ->where('t.kingdom = :kingdom')
            ->setParameter('kingdom', $kingdom)
            ->getQuery()
            ->getResult();
    }
}
