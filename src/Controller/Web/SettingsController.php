<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use App\Entity\Auth\VerificationToken;
use App\Enum\TokenType;
use App\Repository\Auth\UserRepository;
use App\Repository\Auth\VerificationTokenRepository;
use App\Repository\Notification\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class SettingsController extends AbstractController
{
    public function __construct(
        private readonly string $mailerFrom,
        private readonly \Symfony\Contracts\Translation\TranslatorInterface $translator,
    ) {
    }

    #[Route('/app/settings', name: 'app_settings', methods: ['GET'])]
    #[IsGranted('ROLE_PLAYER')]
    public function index(): Response
    {
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/app/settings/change-email', name: 'api_change_email', methods: ['POST'])]
    #[IsGranted('ROLE_PLAYER')]
    public function changeEmail(
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        MailerInterface $mailer,
    ): JsonResponse {
        $csrfToken = $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('api', $csrfToken)) {
            return $this->json(['error' => 'Invalid security token.'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $newEmail = strtolower(trim($data['email'] ?? ''));

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['error' => 'Invalid email format.'], Response::HTTP_BAD_REQUEST);
        }

        /** @var User $user */
        $user = $this->getUser();
        if ($user->getEmail() === $newEmail) {
            return $this->json(['error' => 'This is already your current email address.'], Response::HTTP_BAD_REQUEST);
        }

        if ($userRepository->findOneBy(['email' => $newEmail])) {
            return $this->json(['error' => 'This email address is already in use by another account.'], Response::HTTP_BAD_REQUEST);
        }

        // Generate token for current email confirmation
        $token = new VerificationToken();
        $token->setUser($user);
        $token->setToken(bin2hex(random_bytes(32)));
        $token->setType(TokenType::ChangeEmailOld);
        $token->setExpiresAt(new \DateTimeImmutable('+2 hours'));
        $token->setData(['new_email' => $newEmail]);

        $entityManager->persist($token);
        $entityManager->flush();

        // Send email to original address
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

        $mailer->send($emailObj);

        return $this->json(['message' => 'Confirmation link sent to your current email address.']);
    }

    #[Route('/confirm-email-change/old', name: 'app_confirm_email_change_old', methods: ['GET'])]
    public function confirmEmailChangeOld(
        Request $request,
        VerificationTokenRepository $tokenRepository,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
    ): Response {
        $rawToken = $request->query->getString('token');
        $token = $tokenRepository->findActiveByToken($rawToken);

        if (!$token || TokenType::ChangeEmailOld !== $token->getType()) {
            $this->addFlash('error', 'Invalid or expired email confirmation link.');

            return $this->redirectToRoute('app_home');
        }

        $user = $token->getUser();
        $token->setUsedAt(new \DateTimeImmutable());

        $newEmail = $token->getData()['new_email'] ?? null;
        if (!$newEmail) {
            $this->addFlash('error', 'Invalid email change request payload.');

            return $this->redirectToRoute('app_home');
        }

        // Generate token for new email verification
        $newToken = new VerificationToken();
        $newToken->setUser($user);
        $newToken->setToken(bin2hex(random_bytes(32)));
        $newToken->setType(TokenType::ChangeEmailNew);
        $newToken->setExpiresAt(new \DateTimeImmutable('+2 hours'));
        $newToken->setData(['new_email' => $newEmail]);

        $entityManager->persist($newToken);
        $entityManager->flush();

        // Send email to new address
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

        $mailer->send($emailObj);

        $this->addFlash('success', 'Current email confirmed. A final verification link has been sent to your new email address.');

        return $this->redirectToRoute('app_home');
    }

    #[Route('/confirm-email-change/new', name: 'app_confirm_email_change_new', methods: ['GET'])]
    public function confirmEmailChangeNew(
        Request $request,
        VerificationTokenRepository $tokenRepository,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $rawToken = $request->query->getString('token');
        $token = $tokenRepository->findActiveByToken($rawToken);

        if (!$token || TokenType::ChangeEmailNew !== $token->getType()) {
            $this->addFlash('error', 'Invalid or expired email confirmation link.');

            return $this->redirectToRoute('app_home');
        }

        $user = $token->getUser();
        $token->setUsedAt(new \DateTimeImmutable());

        $newEmail = $token->getData()['new_email'] ?? null;
        if (!$newEmail) {
            $this->addFlash('error', 'Invalid email change request payload.');

            return $this->redirectToRoute('app_home');
        }

        if ($userRepository->findOneBy(['email' => $newEmail])) {
            $this->addFlash('error', 'The new email address is already in use by another account.');

            return $this->redirectToRoute('app_home');
        }

        $user->setEmail($newEmail);
        $entityManager->flush();

        $this->addFlash('success', 'Your email address has been successfully updated!');

        return $this->redirectToRoute('app_home');
    }

    #[Route('/app/settings/cancel-account', name: 'api_cancel_account', methods: ['POST'])]
    #[IsGranted('ROLE_PLAYER')]
    public function cancelAccount(
        Request $request,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
    ): JsonResponse {
        $csrfToken = $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('api', $csrfToken)) {
            return $this->json(['error' => 'Invalid security token.'], Response::HTTP_FORBIDDEN);
        }

        /** @var User $user */
        $user = $this->getUser();

        $token = new VerificationToken();
        $token->setUser($user);
        $token->setToken(bin2hex(random_bytes(32)));
        $token->setType(TokenType::DeleteAccount);
        $token->setExpiresAt(new \DateTimeImmutable('+2 hours'));

        $entityManager->persist($token);
        $entityManager->flush();

        // Send email to user
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

        $mailer->send($emailObj);

        return $this->json(['message' => 'A deletion confirmation link has been sent to your email.']);
    }

    #[Route('/confirm-cancel-account', name: 'app_confirm_cancel_account', methods: ['GET'])]
    public function confirmCancelAccount(
        Request $request,
        VerificationTokenRepository $tokenRepository,
        NotificationRepository $notificationRepository,
        EntityManagerInterface $entityManager,
        TokenStorageInterface $tokenStorage,
    ): Response {
        $rawToken = $request->query->getString('token');
        $token = $tokenRepository->findActiveByToken($rawToken);

        if (!$token || TokenType::DeleteAccount !== $token->getType()) {
            $this->addFlash('error', 'Invalid or expired account cancellation link.');

            return $this->redirectToRoute('app_home');
        }

        $user = $token->getUser();

        // Invalidate current session and log out
        $request->getSession()->invalidate();
        $tokenStorage->setToken(null);

        // Disassociate team
        $team = $user->getTeam();
        if ($team) {
            $team->setUser(null);
            $team->setIsNpc(true);
        }

        // Delete user notifications
        $notifications = $notificationRepository->findBy(['user' => $user]);
        foreach ($notifications as $notification) {
            $entityManager->remove($notification);
        }

        // Delete user verification tokens
        $tokens = $tokenRepository->findBy(['user' => $user]);
        foreach ($tokens as $t) {
            $entityManager->remove($t);
        }

        // Delete user entity
        $entityManager->remove($user);
        $entityManager->flush();

        $this->addFlash('success', 'Your account has been permanently deleted.');

        return $this->redirectToRoute('app_home');
    }
}
