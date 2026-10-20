<?php

declare(strict_types=1);

namespace App\Repository\Item;

use App\Entity\Item\Item;
use App\Entity\Team\Team;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Item>
 */
class ItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Item::class);
    }

    /** @return list<Item> */
    public function findOwnedByTeam(Team $team): array
    {
        /** @var list<Item> $rows */
        $rows = $this->createQueryBuilder('i')
            ->andWhere('i.ownerTeam = :team')
            ->leftJoin('i.equippedHero', 'h')
            ->addSelect('h')
            ->orderBy('i.id', 'ASC')
            ->setParameter('team', $team)
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
