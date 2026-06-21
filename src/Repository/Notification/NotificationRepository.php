<?php

declare(strict_types=1);

namespace App\Repository\Notification;

use App\Entity\Auth\User;
use App\Entity\Notification\Notification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * @return list<Notification>
     */
    public function findForUser(User $user, int $limit = 50, bool $unreadOnly = false, ?int $page = null): array
    {
        $qb = $this->createQueryBuilder('n')
            ->andWhere('n.user = :user')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults(max(1, min($limit, 100)));

        if ($unreadOnly) {
            $qb->andWhere('n.isRead = false');
        }

        if (null !== $page) {
            $qb->setFirstResult(($page - 1) * $limit);
        }

        /* @var list<Notification> */
        return $qb->getQuery()->getResult();
    }

    public function countForUser(User $user, bool $unreadOnly = false): int
    {
        $qb = $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.user = :user')
            ->setParameter('user', $user);

        if ($unreadOnly) {
            $qb->andWhere('n.isRead = false');
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function countUnreadForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.user = :user')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneForUser(int $id, User $user): ?Notification
    {
        /* @var Notification|null */
        return $this->createQueryBuilder('n')
            ->andWhere('n.id = :id')
            ->andWhere('n.user = :user')
            ->setParameter('id', $id)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
