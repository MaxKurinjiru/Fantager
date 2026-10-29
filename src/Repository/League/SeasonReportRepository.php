<?php

declare(strict_types=1);

namespace App\Repository\League;

use App\Entity\Kingdom\Kingdom;
use App\Entity\League\LeagueSeason;
use App\Entity\League\SeasonReport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SeasonReport>
 */
class SeasonReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SeasonReport::class);
    }

    public function findOneBySeason(LeagueSeason $season): ?SeasonReport
    {
        return $this->findOneBy(['season' => $season]);
    }

    public function findOneByKingdomAndNumber(Kingdom $kingdom, int $seasonNumber): ?SeasonReport
    {
        return $this->findOneBy(
            ['kingdom' => $kingdom, 'seasonNumber' => $seasonNumber],
        );
    }

    /**
     * @return list<SeasonReport>
     */
    public function findByKingdomOrdered(Kingdom $kingdom): array
    {
        /** @var list<SeasonReport> $rows */
        $rows = $this->createQueryBuilder('r')
            ->where('r.kingdom = :kingdom')
            ->setParameter('kingdom', $kingdom)
            ->orderBy('r.seasonNumber', \SortDirection::Ascending)
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
