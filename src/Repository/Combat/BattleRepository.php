<?php

declare(strict_types=1);

namespace App\Repository\Combat;

use App\Entity\Combat\Battle;
use App\Entity\FriendlyMatch\FriendlyMatch;
use App\Entity\League\LeagueFixture;
use App\Enum\BattleStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * @extends ServiceEntityRepository<Battle>
 */
class BattleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Battle::class);
    }

    /**
     * @return list<array{id: int, currentRound: int}>
     */
    public function findSimulatingCohortRounds(int $kingdomId, \DateTimeImmutable $scheduledAt): array
    {
        return $this->createQueryBuilder('b')
            ->select('b.id', 'b.currentRound')
            ->where('b.kingdom = :kingdomId')
            ->andWhere('b.scheduledAt = :scheduledAt')
            ->andWhere('b.status = :status')
            ->andWhere(sprintf(
                'EXISTS (SELECT f.id FROM %s f WHERE f.battle = b) OR EXISTS (SELECT fm.id FROM %s fm WHERE fm.battle = b)',
                LeagueFixture::class,
                FriendlyMatch::class,
            ))
            ->setParameter('kingdomId', $kingdomId)
            ->setParameter('scheduledAt', $scheduledAt)
            ->setParameter('status', BattleStatus::Simulating)
            ->orderBy('b.id', SortDirection::Ascending)
            ->getQuery()
            ->getArrayResult();
    }
}
