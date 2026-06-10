<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\Auth\User;
use App\Entity\Auth\VerificationToken;
use App\Enum\TokenType;
use App\Repository\Auth\UserRepository;
use App\Repository\Auth\VerificationTokenRepository;
use App\Repository\Kingdom\KingdomRepository;
use App\Service\Kingdom\KingdomService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class RegistrationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly MailerInterface $mailer,
        private readonly KingdomRepository $kingdomRepository,
        private readonly KingdomService $kingdomService,
        private readonly UserRepository $userRepository,
        private readonly VerificationTokenRepository $tokenRepository,
        private readonly SlugGenerator $slugGenerator,
    ) {
    }

    /**
     * @return array{success: bool, errors: array<string, string>}
     */
    public function register(
        string $email,
        string $plainPassword,
        string $displayName,
        int $kingdomId,
    ): array {
        $errors = $this->validate($email, $plainPassword, $displayName, $kingdomId);

        if ([] !== $errors) {
            return ['success' => false, 'errors' => $errors];
        }

        $kingdom = $this->kingdomRepository->find($kingdomId);
        if (!$kingdom) {
            throw new \InvalidArgumentException('Kingdom not found');
        }

        $user = new User();
        $user->setEmail(strtolower(trim($email)));
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $user->setDisplayName(trim($displayName));
        $user->setDisplayNameSlug($this->slugGenerator->generate($displayName));
        $user->setKingdom($kingdom);
        $user->setLocale($kingdom->getLanguage());
        $user->setIsVerified(false);
        $user->setRoles([]);

        $this->em->persist($user);

        $token = new VerificationToken();
        $token->setUser($user);
        $token->setToken(bin2hex(random_bytes(32)));
        $token->setType(TokenType::EmailVerify);
        $token->setExpiresAt(new \DateTimeImmutable('+24 hours'));

        $this->em->persist($token);
        $this->em->flush();

        $this->sendVerificationEmail($user, $token);

        return ['success' => true, 'errors' => []];
    }

    public function resendVerification(string $email): void
    {
        $user = $this->userRepository->findOneBy(['email' => strtolower(trim($email))]);

        if (!$user || $user->isVerified()) {
            return; // silent — do not reveal existence
        }

        // Invalidate old tokens
        $old = $this->tokenRepository->findBy(['user' => $user, 'type' => TokenType::EmailVerify]);
        foreach ($old as $t) {
            $t->setUsedAt(new \DateTimeImmutable());
        }

        $token = new VerificationToken();
        $token->setUser($user);
        $token->setToken(bin2hex(random_bytes(32)));
        $token->setType(TokenType::EmailVerify);
        $token->setExpiresAt(new \DateTimeImmutable('+24 hours'));

        $this->em->persist($token);
        $this->em->flush();

        $this->sendVerificationEmail($user, $token);
    }

    /** @return array<string, string> */
    private function validate(string $email, string $password, string $displayName, int $kingdomId): array
    {
        $errors = [];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'register.email.invalid';
        } elseif (strlen($email) > 180) {
            $errors['email'] = 'register.email.too_long';
        } elseif ($this->userRepository->findOneBy(['email' => strtolower(trim($email))])) {
            $errors['email'] = 'register.email.already_registered';
        }

        if (strlen($password) < 8) {
            $errors['password'] = 'register.password.too_short';
        } elseif (strlen($password) > 4096) {
            $errors['password'] = 'register.password.too_long';
        }

        $trimmedName = trim($displayName);
        if ('' === $trimmedName) {
            $errors['display_name'] = 'register.display_name.required';
        } elseif (strlen($trimmedName) > 50) {
            $errors['display_name'] = 'register.display_name.too_long';
        } else {
            $slug = $this->slugGenerator->generate($trimmedName);
            if ($this->userRepository->findOneBy(['displayNameSlug' => $slug])) {
                $errors['display_name'] = 'register.display_name.taken';
            }
        }

        $kingdom = $this->kingdomRepository->find($kingdomId);
        if (!$kingdom) {
            $errors['kingdom'] = 'register.kingdom.invalid';
        } elseif (!$this->kingdomService->hasActiveSeason($kingdom)) {
            $errors['kingdom'] = 'register.kingdom.no_active_season';
        } elseif (!$this->kingdomService->hasCapacity($kingdom)) {
            $errors['kingdom'] = 'register.kingdom.full';
        }

        return $errors;
    }

    private function sendVerificationEmail(User $user, VerificationToken $token): void
    {
        $email = (new TemplatedEmail())
            ->to($user->getEmail())
            ->subject('Fantager — verify your email')
            ->htmlTemplate('email/verify_email.html.twig')
            ->context([
                'user' => $user,
                'token' => $token->getToken(),
            ]);

        $this->mailer->send($email);
    }
}
