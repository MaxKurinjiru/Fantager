<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Exception\InactiveSeasonException;
use App\Service\Auth\RegistrationService;
use App\Service\Auth\VerificationService;
use App\Service\Kingdom\KingdomService;
use App\Service\Translation\UserMessageTranslator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegisterController extends AbstractController
{
    public function __construct(
        private readonly RegistrationService $registrationService,
        private readonly VerificationService $verificationService,
        private readonly KingdomService $kingdomService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly RateLimiterFactoryInterface $registerLimiter,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
        private readonly UserMessageTranslator $userMessages,
    ) {
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $errors = [];
        $formData = [];

        if ($request->isMethod('POST')) {
            // Rate limiting
            $limiter = $this->registerLimiter->create($request->getClientIp());
            $limit = $limiter->consume(1);

            if (!$limit->isAccepted()) {
                return $this->json(
                    ['error' => $this->userMessages->trans('flash.too_many_registration_attempts')],
                    Response::HTTP_TOO_MANY_REQUESTS,
                    ['Retry-After' => $limit->getRetryAfter()->getTimestamp() - time()]
                );
            }

            // CSRF
            $csrfToken = $request->request->getString('_token');
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('register', $csrfToken))) {
                $this->addFlash('error', $this->userMessages->trans('flash.invalid_csrf'));

                return $this->redirectToRoute('app_register');
            }

            $formData = [
                'email' => $request->request->getString('email'),
                'display_name' => $request->request->getString('display_name'),
                'kingdom_id' => $request->request->getInt('kingdom_id'),
            ];

            $result = $this->registrationService->register(
                $formData['email'],
                $request->request->getString('password'),
                $formData['display_name'],
                $formData['kingdom_id'],
            );

            if ($result['success']) {
                $this->addFlash('success', $this->userMessages->trans('flash.account_created'));

                return $this->redirectToRoute('app_register_success');
            }

            $errors = $result['errors'];
        }

        return $this->render('auth/register.html.twig', [
            'open_modal' => 'register',
            'kingdoms' => $this->kingdomService->listWithCapacity(),
            'errors' => $errors,
            'form_data' => $formData,
        ]);
    }

    #[Route('/register/success', name: 'app_register_success', methods: ['GET'])]
    public function success(): Response
    {
        return $this->render('auth/register_success.html.twig');
    }

    #[Route('/verify-email', name: 'app_verify_email', methods: ['GET'])]
    public function verifyEmail(Request $request): Response
    {
        $rawToken = $request->query->getString('token');

        if ('' === $rawToken) {
            $this->addFlash('error', $this->translator->trans('verification.token_invalid', [], 'validators'));

            return $this->redirectToRoute('app_login');
        }

        try {
            $user = $this->verificationService->verify($rawToken);

            if (!$user) {
                $this->addFlash('error', $this->translator->trans('verification.token_expired', [], 'validators'));

                return $this->redirectToRoute('app_login');
            }
        } catch (InactiveSeasonException) {
            $this->addFlash('error', $this->translator->trans('register.kingdom.no_active_season', [], 'validators'));

            return $this->redirectToRoute('app_login');
        }
        // Auto-login the user
        $response = $this->security->login($user, 'form_login', 'main');

        $this->addFlash('success', $this->userMessages->trans('flash.email_verified'));

        return $response ?? $this->redirectToRoute('app_dashboard');
    }

    #[Route('/register/resend-verification', name: 'app_register_resend', methods: ['POST'])]
    public function resend(Request $request): Response
    {
        $limiter = $this->registerLimiter->create($request->getClientIp());
        $limit = $limiter->consume(1);

        if (!$limit->isAccepted()) {
            $this->addFlash('error', $this->userMessages->trans('flash.too_many_requests'));

            return $this->redirectToRoute('app_register_success');
        }

        $csrfToken = $request->request->getString('_token');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('register_resend', $csrfToken))) {
            $this->addFlash('error', $this->userMessages->trans('error.invalid_csrf'));

            return $this->redirectToRoute('app_register_success');
        }

        $email = $request->request->getString('email');
        $this->registrationService->resendVerification($email);

        $this->addFlash('success', $this->userMessages->trans('flash.verification_resent'));

        return $this->redirectToRoute('app_register_success');
    }
}
