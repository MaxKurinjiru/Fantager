<?php

declare(strict_types=1);

namespace App\Repository\Graveyard;

use App\Entity\Graveyard\GraveyardMemorial;
use App\Entity\Team\Team;
use App\Enum\HeroRole;
use App\Enum\MemorialCause;
use App\Enum\Race;
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

    /**
     * @return list<GraveyardMemorial>
     */
    public function findByTeamFiltered(
        Team $team,
        ?HeroRole $role = null,
        ?MemorialCause $cause = null,
        ?Race $race = null,
        ?string $search = null,
    ): array {
        $qb = $this->createQueryBuilder('m')
            ->where('m.team = :team')
            ->setParameter('team', $team)
            ->orderBy('m.departedAt', 'DESC')
            ->addOrderBy('m.id', 'DESC');

        if (null !== $role) {
            $qb->andWhere('m.roleAtDeparture = :role')
                ->setParameter('role', $role);
        }

        if (null !== $cause) {
            $qb->andWhere('m.cause = :cause')
                ->setParameter('cause', $cause);
        }

        if (null !== $race) {
            $qb->andWhere('m.race = :race')
                ->setParameter('race', $race);
        }

        if (null !== $search && '' !== trim($search)) {
            $qb->andWhere('LOWER(m.name) LIKE :search')
                ->setParameter('search', '%'.mb_strtolower(trim($search)).'%');
        }

        /** @var list<GraveyardMemorial> $records */
        $records = $qb->getQuery()->getResult();

        return $records;
    }

    public function findOneForTeam(int $id, Team $team): ?GraveyardMemorial
    {
        return $this->findOneBy(['id' => $id, 'team' => $team]);
    }

    /**
     * @return array<string, int>
     */
    public function countByCauseForTeam(Team $team): array
    {
        /** @var list<array{cause: MemorialCause, count: string|int}> $rows */
        $rows = $this->createQueryBuilder('m')
            ->select('m.cause AS cause, COUNT(m.id) AS count')
            ->where('m.team = :team')
            ->setParameter('team', $team)
            ->groupBy('m.cause')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row['cause']->value] = (int) $row['count'];
        }

        return $counts;
    }

    public function averageAgeForTeam(Team $team): ?float
    {
        $average = $this->createQueryBuilder('m')
            ->select('AVG(m.age)')
            ->where('m.team = :team')
            ->setParameter('team', $team)
            ->getQuery()
            ->getSingleScalarResult();

        return null !== $average ? round((float) $average, 1) : null;
    }
}
