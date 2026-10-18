<?php

declare(strict_types=1);

namespace App\Repository\Team;

use App\Entity\Team\Team;
use App\Entity\Team\TeamChronicle;
use App\Enum\ChronicleCategory;
use App\Enum\ChronicleEventType;
use App\Service\TeamChronicle\ChroniclePeriodWindow;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
            ->orderBy('a.createdAt', \SortDirection::Descending)
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
        ?ChroniclePeriodWindow $period = null,
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->where('a.team = :team')
            ->setParameter('team', $team);

        $this->applyTypeFilter($qb, $type, $category);
        $this->applyPeriod($qb, $period);

        switch ($sort) {
            case 'date-asc':
                $qb->orderBy('a.createdAt', \SortDirection::Ascending);
                break;
            case 'date-desc':
            default:
                $qb->orderBy('a.createdAt', \SortDirection::Descending);
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
        ?ChroniclePeriodWindow $period = null,
    ): int {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.team = :team')
            ->setParameter('team', $team);

        $this->applyTypeFilter($qb, $type, $category);
        $this->applyPeriod($qb, $period);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Ownership boundaries, oldest first: team established, player joined, player released.
     *
     * @return list<TeamChronicle>
     */
    public function findOwnershipMarkers(Team $team): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.team = :team')
            ->andWhere('a.type IN (:types)')
            ->setParameter('team', $team)
            ->setParameter('types', [
                ChronicleEventType::TeamEstablished,
                ChronicleEventType::PlayerJoined,
                ChronicleEventType::PlayerReleased,
            ])
            ->orderBy('a.createdAt', \SortDirection::Ascending)
            ->addOrderBy('a.id', \SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }

    private function applyTypeFilter(QueryBuilder $qb, ?ChronicleEventType $type, ?ChronicleCategory $category): void
    {
        if (null !== $type) {
            $qb->andWhere('a.type = :type')
                ->setParameter('type', $type);

            return;
        }

        if (null !== $category && ChronicleCategory::All !== $category) {
            $types = $category->types();
            if (null !== $types && [] !== $types) {
                $qb->andWhere('a.type IN (:types)')
                    ->setParameter('types', $types);
            }
        }
    }

    private function applyPeriod(QueryBuilder $qb, ?ChroniclePeriodWindow $period): void
    {
        if (null === $period) {
            return;
        }

        $qb->andWhere($period->fromInclusive ? 'a.createdAt >= :periodFrom' : 'a.createdAt > :periodFrom')
            ->setParameter('periodFrom', $period->from);

        if (null === $period->to) {
            return;
        }

        $qb->andWhere($period->toInclusive ? 'a.createdAt <= :periodTo' : 'a.createdAt < :periodTo')
            ->setParameter('periodTo', $period->to);
    }
}
