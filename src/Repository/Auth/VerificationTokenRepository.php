<?php

declare(strict_types=1);

namespace App\Repository\Auth;

use App\Entity\Auth\VerificationToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class VerificationTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VerificationToken::class);
    }

    public function findActiveByToken(string $token): ?VerificationToken
    {
        return $this->createQueryBuilder('vt')
            ->where('vt.token = :token')
            ->andWhere('vt.usedAt IS NULL')
            ->andWhere('vt.expiresAt > :now')
            ->setParameter('token', $token)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }
}
