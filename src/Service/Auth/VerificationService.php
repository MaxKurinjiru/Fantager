<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\Auth\User;
use App\Enum\TokenType;
use App\Exception\InactiveSeasonException;
use App\Repository\Auth\VerificationTokenRepository;
use App\Service\Kingdom\KingdomService;
use Doctrine\ORM\EntityManagerInterface;

class VerificationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly VerificationTokenRepository $tokenRepository,
        private readonly KingdomService $kingdomService,
    ) {
    }

    public function verify(string $rawToken): ?User
    {
        $token = $this->tokenRepository->findActiveByToken($rawToken);

        if (!$token || TokenType::EmailVerify !== $token->getType()) {
            return null;
        }

        $user = $token->getUser();
        $kingdom = $user->getKingdom();
        if ($kingdom && !$this->kingdomService->hasActiveSeason($kingdom)) {
            throw new InactiveSeasonException();
        }

        $user->setIsVerified(true);
        $user->setLastActivityAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
        $user->setInactiveWarningSentAt(null);

        $token->setUsedAt(new \DateTimeImmutable());

        $this->em->flush();

        return $user;
    }
}
