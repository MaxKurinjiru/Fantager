<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\Auth\User;
use App\Entity\Team\Team;
use App\Enum\TokenType;
use App\Repository\Auth\VerificationTokenRepository;
use App\Repository\Team\TeamRepository;
use Doctrine\ORM\EntityManagerInterface;

class VerificationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly VerificationTokenRepository $tokenRepository,
        private readonly TeamRepository $teamRepository,
    ) {
    }

    public function verify(string $rawToken): ?User
    {
        $token = $this->tokenRepository->findActiveByToken($rawToken);

        if (!$token || TokenType::EmailVerify !== $token->getType()) {
            return null;
        }

        $user = $token->getUser();
        $user->setIsVerified(true);

        $token->setUsedAt(new \DateTimeImmutable());

        $this->em->flush();

        return $user;
    }

    public function assignNpcTeam(User $user): ?Team
    {
        $kingdom = $user->getKingdom();
        if (!$kingdom) {
            return null;
        }

        $team = $this->teamRepository->findAvailableNpcTeam($kingdom->getId());
        if (!$team) {
            return null;
        }

        $team->setUser($user);
        $team->setIsNpc(false);
        $this->em->flush();

        return $team;
    }
}
