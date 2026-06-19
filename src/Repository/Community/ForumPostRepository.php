<?php

declare(strict_types=1);

namespace App\Repository\Community;

use App\Entity\Community\ForumPost;
use App\Entity\Community\ForumThread;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ForumPostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ForumPost::class);
    }

    public function getOriginalPost(ForumThread $thread): ?ForumPost
    {
        return $this->createQueryBuilder('p')
            ->where('p.thread = :thread')
            ->setParameter('thread', $thread)
            ->orderBy('p.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<ForumPost>
     */
    public function findRepliesPage(ForumThread $thread, int $page, int $limit): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.thread = :thread')
            ->setParameter('thread', $thread)
            ->orderBy('p.createdAt', 'ASC')
            ->setFirstResult((($page - 1) * $limit) + 1)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countReplies(ForumThread $thread): int
    {
        $total = (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.thread = :thread')
            ->setParameter('thread', $thread)
            ->getQuery()
            ->getSingleScalarResult();

        return max(0, $total - 1);
    }
}
