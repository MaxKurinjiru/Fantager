<?php

declare(strict_types=1);

namespace App\Repository\Hero;

use App\Entity\Hero\Hero;
use App\Entity\Hero\HeroChronicle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HeroChronicle>
 */
class HeroChronicleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HeroChronicle::class);
    }

    /**
     * @return list<HeroChronicle>
     */
    public function findRecentByHero(Hero|int $hero, int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('a');

        if ($hero instanceof Hero) {
            $qb->where('a.hero = :hero OR a.originalHeroId = :heroId')
                ->setParameter('hero', $hero)
                ->setParameter('heroId', $hero->getId());
        } else {
            $qb->where('a.originalHeroId = :heroId')
                ->setParameter('heroId', $hero);
        }

        return $qb->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
