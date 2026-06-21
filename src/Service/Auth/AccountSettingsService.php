<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\Auth\User;
use App\Entity\Auth\VerificationToken;
use App\Enum\ChronicleReleaseReason;
use App\Enum\TokenType;
use App\Exception\UserFacingException;
use App\Repository\Auth\UserRepository;
use App\Repository\Auth\VerificationTokenRepository;
use App\Repository\Notification\NotificationRepository;
use App\Service\TeamChronicle\TeamChronicleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AccountSettingsService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
        private readonly VerificationTokenRepository $tokenRepository,
        private readonly NotificationRepository $notificationRepository,
        private readonly TeamChronicleService $teamChronicleService,
        private readonly MailerInterface $mailer,
        private readonly string $mailerFrom,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function requestEmailChange(User $user, string $newEmail): void
    {
        $newEmail = strtolower(trim($newEmail));

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            throw new UserFacingException('error.invalid_email_format');
        }

        if ($user->getEmail() === $newEmail) {
            throw new UserFacingException('error.email_already_current');
        }

        if ($this->userRepository->findOneBy(['email' => $newEmail])) {
            throw new UserFacingException('error.email_already_in_use');
        }

        $token = new VerificationToken();
        $token->setUser($user);
        $token->setToken(bin2hex(random_bytes(32)));
        $token->setType(TokenType::ChangeEmailOld);
        $token->setExpiresAt(new \DateTimeImmutable('+2 hours'));
        $token->setData(['new_email' => $newEmail]);

        $this->em->persist($token);
        $this->em->flush();

        $emailObj = (new TemplatedEmail())
            ->from($this->mailerFrom)
            ->to($user->getEmail())
            ->subject($this->translator->trans('email.change_email_old.subject', [], 'messages', $user->getLocale()))
            ->htmlTemplate('email/change_email_old.html.twig')
            ->context([
                'user' => $user,
                'new_email' => $newEmail,
                'token' => $token->getToken(),
                'locale' => $user->getLocale(),
            ]);

        $this->mailer->send($emailObj);
    }

    public function confirmEmailChangeFromOldToken(string $rawToken): void
    {
        $token = $this->tokenRepository->findActiveByToken($rawToken);

        if (!$token || TokenType::ChangeEmailOld !== $token->getType()) {
            throw new UserFacingException('error.invalid_email_confirmation_link');
        }

        $user = $token->getUser();
        $token->setUsedAt(new \DateTimeImmutable());

        $newEmail = $token->getData()['new_email'] ?? null;
        if (!$newEmail || !\is_string($newEmail)) {
            throw new UserFacingException('error.invalid_email_change_payload');
        }

        $newToken = new VerificationToken();
        $newToken->setUser($user);
        $newToken->setToken(bin2hex(random_bytes(32)));
        $newToken->setType(TokenType::ChangeEmailNew);
        $newToken->setExpiresAt(new \DateTimeImmutable('+2 hours'));
        $newToken->setData(['new_email' => $newEmail]);

        $this->em->persist($newToken);
        $this->em->flush();

        $emailObj = (new TemplatedEmail())
            ->from($this->mailerFrom)
            ->to($newEmail)
            ->subject($this->translator->trans('email.change_email_new.subject', [], 'messages', $user->getLocale()))
            ->htmlTemplate('email/change_email_new.html.twig')
            ->context([
                'user' => $user,
                'token' => $newToken->getToken(),
                'locale' => $user->getLocale(),
            ]);

        $this->mailer->send($emailObj);
    }

    public function confirmEmailChangeFromNewToken(string $rawToken): void
    {
        $token = $this->tokenRepository->findActiveByToken($rawToken);

        if (!$token || TokenType::ChangeEmailNew !== $token->getType()) {
            throw new UserFacingException('error.invalid_email_confirmation_link');
        }

        $user = $token->getUser();
        $token->setUsedAt(new \DateTimeImmutable());

        $newEmail = $token->getData()['new_email'] ?? null;
        if (!$newEmail || !\is_string($newEmail)) {
            throw new UserFacingException('error.invalid_email_change_payload');
        }

        if ($this->userRepository->findOneBy(['email' => $newEmail])) {
            throw new UserFacingException('error.email_already_in_use');
        }

        $user->setEmail($newEmail);
        $this->em->flush();
    }

    public function requestAccountDeletion(User $user): void
    {
        $token = new VerificationToken();
        $token->setUser($user);
        $token->setToken(bin2hex(random_bytes(32)));
        $token->setType(TokenType::DeleteAccount);
        $token->setExpiresAt(new \DateTimeImmutable('+2 hours'));

        $this->em->persist($token);
        $this->em->flush();

        $emailObj = (new TemplatedEmail())
            ->from($this->mailerFrom)
            ->to($user->getEmail())
            ->subject($this->translator->trans('email.cancel_account.subject', [], 'messages', $user->getLocale()))
            ->htmlTemplate('email/cancel_account.html.twig')
            ->context([
                'user' => $user,
                'token' => $token->getToken(),
                'locale' => $user->getLocale(),
            ]);

        $this->mailer->send($emailObj);
    }

    public function confirmAccountDeletion(string $rawToken): User
    {
        $token = $this->tokenRepository->findActiveByToken($rawToken);

        if (!$token || TokenType::DeleteAccount !== $token->getType()) {
            throw new UserFacingException('error.invalid_account_cancellation_link');
        }

        $user = $token->getUser();

        $team = $user->getTeam();
        if ($team) {
            $this->teamChronicleService->recordPlayerReleased(
                $team,
                $user,
                ChronicleReleaseReason::AccountDeleted,
            );
            $team->setUser(null);
            $team->setIsNpc(true);
        }

        $notifications = $this->notificationRepository->findBy(['user' => $user]);
        foreach ($notifications as $notification) {
            $this->em->remove($notification);
        }

        $tokens = $this->tokenRepository->findBy(['user' => $user]);
        foreach ($tokens as $existingToken) {
            $this->em->remove($existingToken);
        }

        $this->em->remove($user);
        $this->em->flush();

        return $user;
    }
}
