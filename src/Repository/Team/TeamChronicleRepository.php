<?php

declare(strict_types=1);

namespace App\Repository\Team;

use App\Entity\Team\Team;
use App\Entity\Team\TeamChronicle;
use App\Enum\ChronicleCategory;
use App\Enum\ChronicleEventType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TeamChronicle>
 */
class TeamChronicleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TeamChronicle::class);
    }

    /**
     * @return list<TeamChronicle>
     */
    public function findRecentByTeam(Team $team, int $limit = 5): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.team = :team')
            ->setParameter('team', $team)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<TeamChronicle>
     */
    public function findByTeamFiltered(
        Team $team,
        ?ChronicleEventType $type = null,
        ?ChronicleCategory $category = null,
        ?string $sort = 'date-desc',
        ?int $limit = null,
        ?int $offset = null,
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->where('a.team = :team')
            ->setParameter('team', $team);

        if (null !== $type) {
            $qb->andWhere('a.type = :type')
                ->setParameter('type', $type);
        } elseif (null !== $category && ChronicleCategory::All !== $category) {
            $types = $category->types();
            if (null !== $types && [] !== $types) {
                $qb->andWhere('a.type IN (:types)')
                    ->setParameter('types', $types);
            }
        }

        switch ($sort) {
            case 'date-asc':
                $qb->orderBy('a.createdAt', 'ASC');
                break;
            case 'date-desc':
            default:
                $qb->orderBy('a.createdAt', 'DESC');
                break;
        }

        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        if (null !== $offset) {
            $qb->setFirstResult($offset);
        }

        return $qb->getQuery()->getResult();
    }

    public function countByTeamFiltered(
        Team $team,
        ?ChronicleEventType $type = null,
        ?ChronicleCategory $category = null,
    ): int {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.team = :team')
            ->setParameter('team', $team);

        if (null !== $type) {
            $qb->andWhere('a.type = :type')
                ->setParameter('type', $type);
        } elseif (null !== $category && ChronicleCategory::All !== $category) {
            $types = $category->types();
            if (null !== $types && [] !== $types) {
                $qb->andWhere('a.type IN (:types)')
                    ->setParameter('types', $types);
            }
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
