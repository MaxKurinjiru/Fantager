<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\Auth\VerificationToken;
use App\Enum\TokenType;
use App\Repository\Auth\UserRepository;
use App\Repository\Auth\VerificationTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class PasswordResetService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
        private readonly VerificationTokenRepository $tokenRepository,
        private readonly MailerInterface $mailer,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function requestReset(string $email): void
    {
        $user = $this->userRepository->findOneBy(['email' => strtolower(trim($email))]);

        if (!$user) {
            return; // silent — do not reveal existence
        }

        // Invalidate existing password-reset tokens
        $old = $this->tokenRepository->findBy(['user' => $user, 'type' => TokenType::PasswordReset]);
        foreach ($old as $t) {
            $t->setUsedAt(new \DateTimeImmutable());
        }

        $token = new VerificationToken();
        $token->setUser($user);
        $token->setToken(bin2hex(random_bytes(32)));
        $token->setType(TokenType::PasswordReset);
        $token->setExpiresAt(new \DateTimeImmutable('+1 hour'));

        $this->em->persist($token);
        $this->em->flush();

        $resetEmail = (new TemplatedEmail())
            ->to($user->getEmail())
            ->subject('Fantager — reset your password')
            ->htmlTemplate('email/password_reset.html.twig')
            ->context([
                'user' => $user,
                'token' => $token->getToken(),
            ]);

        $this->mailer->send($resetEmail);
    }

    /**
     * @return array{success: bool, error: string|null}
     */
    public function resetPassword(string $rawToken, string $newPassword): array
    {
        if (strlen($newPassword) < 8 || strlen($newPassword) > 4096) {
            return ['success' => false, 'error' => 'password_reset.password.invalid_length'];
        }

        $token = $this->tokenRepository->findActiveByToken($rawToken);

        if (!$token || TokenType::PasswordReset !== $token->getType()) {
            return ['success' => false, 'error' => 'password_reset.token.invalid'];
        }

        $user = $token->getUser();
        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));

        $token->setUsedAt(new \DateTimeImmutable());

        $this->em->flush();

        return ['success' => true, 'error' => null];
    }
}
