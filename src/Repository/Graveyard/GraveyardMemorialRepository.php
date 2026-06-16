<?php

declare(strict_types=1);

namespace App\Repository\Graveyard;

use App\Entity\Graveyard\GraveyardMemorial;
use App\Entity\Team\Team;
use App\Enum\HeroRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GraveyardMemorial>
 */
class GraveyardMemorialRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GraveyardMemorial::class);
    }

    /**
     * @return list<GraveyardMemorial>
     */
    public function findByTeamOrdered(Team $team, ?HeroRole $role = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->where('m.team = :team')
            ->setParameter('team', $team)
            ->orderBy('m.departedAt', 'DESC')
            ->addOrderBy('m.id', 'DESC');

        if (null !== $role) {
            $qb->andWhere('m.roleAtDeparture = :role')
                ->setParameter('role', $role);
        }

        /** @var list<GraveyardMemorial> $records */
        $records = $qb->getQuery()->getResult();

        return $records;
    }

    public function countByTeam(Team $team): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.team = :team')
            ->setParameter('team', $team)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
