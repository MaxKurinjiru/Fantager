<?php

declare(strict_types=1);

namespace App\Repository\Kingdom;

use App\Entity\Kingdom\Kingdom;
use App\Entity\Kingdom\KingdomTickLog;
use App\Enum\TickType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<KingdomTickLog>
 */
class KingdomTickLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, KingdomTickLog::class);
    }

    /**
     * Gets the latest completed scheduledAt datetime for a specific tick type.
     */
    public function getLatestCompletedTickTime(Kingdom $kingdom, TickType $tickType): ?\DateTimeImmutable
    {
        /** @var KingdomTickLog|null $latest */
        $latest = $this->createQueryBuilder('l')
            ->where('l.kingdom = :kingdom')
            ->andWhere('l.tickType = :tickType')
            ->andWhere('l.status = :status')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('tickType', $tickType)
            ->setParameter('status', 'completed')
            ->orderBy('l.scheduledAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $latest?->getScheduledAt();
    }
}
