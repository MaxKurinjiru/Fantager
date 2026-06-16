<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Entity\Auth\User;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;
use App\Enum\NotificationType;
use App\Service\Economy\FinancialCrisisService;
use App\Service\Notification\NotificationHelper;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class PlayerInactivityService
{
    public const WARNING_DAYS = 21;
    public const RELEASE_DAYS = 28;

    public function __construct(
        private readonly NotificationHelper $notificationHelper,
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly string $mailerFrom,
    ) {
    }

    public function processDailyInactivityTick(Kingdom $kingdom, \DateTimeImmutable $scheduledAt): void
    {
        $releaseThreshold = $scheduledAt->modify(sprintf('-%d days', self::RELEASE_DAYS));
        $warningThreshold = $scheduledAt->modify(sprintf('-%d days', self::WARNING_DAYS));

        /** @var list<User> $users */
        $users = $this->em->getRepository(User::class)
            ->createQueryBuilder('u')
            ->innerJoin('u.team', 't')
            ->where('u.kingdom = :kingdom')
            ->andWhere('u.isVerified = true')
            ->andWhere('t.isNpc = false')
            ->setParameter('kingdom', $kingdom)
            ->getQuery()
            ->getResult();

        foreach ($users as $user) {
            $team = $user->getTeam();
            if (!$team instanceof Team || $team->isNpc()) {
                continue;
            }

            $lastActivity = $this->resolveLastActivityAt($user);

            if ($lastActivity <= $releaseThreshold) {
                $this->executeInactivityRelease($team, $user);

                continue;
            }

            if ($lastActivity <= $warningThreshold && null === $user->getInactiveWarningSentAt()) {
                $this->sendInactivityWarning($user, $team, $scheduledAt);
            }
        }

        $this->em->flush();
    }

    public function executeInactivityRelease(Team $team, User $user): void
    {
        $this->logger->warning(sprintf(
            'Inactive player team release: team ID %d (%s), user ID %d (%s), last activity %s',
            $team->getId(),
            $team->getName(),
            $user->getId(),
            $user->getEmail(),
            $this->resolveLastActivityAt($user)->format(\DateTimeInterface::ATOM)
        ));

        $team->setUser(null);
        $team->setIsNpc(true);

        $user->setTeam(null);
        $user->setInactiveWarningSentAt(null);
        $user->setTeamReassignmentAvailableAt(
            (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                ->modify(sprintf('+%d days', FinancialCrisisService::REASSIGNMENT_COOLDOWN_DAYS))
        );

        $this->notificationHelper->sendNotification(
            $user,
            NotificationType::System,
            'Team released due to inactivity',
            sprintf(
                'Your team "%s" was released because you have been inactive for %d days. You may claim a new team after %d days.',
                $team->getName(),
                self::RELEASE_DAYS,
                FinancialCrisisService::REASSIGNMENT_COOLDOWN_DAYS
            )
        );

        $this->sendReleaseEmail($user, $team);
    }

    public function calculateInactiveDays(User $user, ?\DateTimeImmutable $reference = null): int
    {
        $reference ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $lastActivity = $this->resolveLastActivityAt($user);

        return (int) $lastActivity->diff($reference)->days;
    }

    private function resolveLastActivityAt(User $user): \DateTimeImmutable
    {
        return $user->getLastActivityAt() ?? $user->getCreatedAt();
    }

    private function sendInactivityWarning(User $user, Team $team, \DateTimeImmutable $scheduledAt): void
    {
        $inactiveDays = $this->calculateInactiveDays($user, $scheduledAt);
        $daysUntilRelease = max(0, self::RELEASE_DAYS - $inactiveDays);

        $user->setInactiveWarningSentAt($scheduledAt);

        $this->notificationHelper->sendNotification(
            $user,
            NotificationType::System,
            'Inactivity warning',
            sprintf(
                'Your team "%s" will be released in %d days if you do not log in and play. Log in to keep your team.',
                $team->getName(),
                $daysUntilRelease
            )
        );

        $this->sendWarningEmail($user, $team, $daysUntilRelease);

        $this->logger->info(sprintf(
            'Inactive player warning sent: user ID %d (%s), team ID %d, %d days until release',
            $user->getId(),
            $user->getEmail(),
            $team->getId(),
            $daysUntilRelease
        ));
    }

    private function sendWarningEmail(User $user, Team $team, int $daysUntilRelease): void
    {
        $locale = $user->getLocale();

        $email = (new TemplatedEmail())
            ->from($this->mailerFrom)
            ->to($user->getEmail())
            ->subject($this->translator->trans('email.inactivity_warning.subject', [], 'messages', $locale))
            ->htmlTemplate('email/inactivity_warning.html.twig')
            ->context([
                'user' => $user,
                'team' => $team,
                'daysUntilRelease' => $daysUntilRelease,
                'locale' => $locale,
            ]);

        $this->mailer->send($email);
    }

    private function sendReleaseEmail(User $user, Team $team): void
    {
        $locale = $user->getLocale();

        $email = (new TemplatedEmail())
            ->from($this->mailerFrom)
            ->to($user->getEmail())
            ->subject($this->translator->trans('email.inactivity_release.subject', [], 'messages', $locale))
            ->htmlTemplate('email/inactivity_release.html.twig')
            ->context([
                'user' => $user,
                'team' => $team,
                'cooldownDays' => FinancialCrisisService::REASSIGNMENT_COOLDOWN_DAYS,
                'locale' => $locale,
            ]);

        $this->mailer->send($email);
    }
}
