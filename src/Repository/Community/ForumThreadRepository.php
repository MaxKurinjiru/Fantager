<?php

declare(strict_types=1);

namespace App\Repository\Community;

use App\Entity\Community\ForumThread;
use App\Entity\Kingdom\Kingdom;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ForumThreadRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumThread::class);
    }

    /**
     * @return list<ForumThread>
     */
    public function findForKingdomListing(
        Kingdom $kingdom,
        ?string $category = null,
        ?string $search = null,
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->distinct()
            ->leftJoin('t.authorTeam', 'author')->addSelect('author')
            ->leftJoin('t.posts', 'p')->addSelect('p')
            ->where('t.kingdom = :kingdom')
            ->setParameter('kingdom', $kingdom);

        if (null !== $category && '' !== $category && 'all' !== $category) {
            $qb->andWhere('t.category = :category')
                ->setParameter('category', $category);
        }

        $normalizedSearch = null !== $search ? trim($search) : '';
        if ('' !== $normalizedSearch) {
            $qb->andWhere('LOWER(t.title) LIKE :search OR LOWER(p.body) LIKE :search')
                ->setParameter('search', '%'.mb_strtolower($normalizedSearch).'%');
        }

        /** @var list<ForumThread> $threads */
        $threads = $qb->getQuery()->getResult();

        return $threads;
    }
}
