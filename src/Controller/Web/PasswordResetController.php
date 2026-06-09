<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Service\Auth\PasswordResetService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class PasswordResetController extends AbstractController
{
    public function __construct(
        private readonly PasswordResetService $passwordResetService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly RateLimiterFactoryInterface $passwordResetLimiter,
    ) {
    }

    #[Route('/password-reset', name: 'app_password_reset', methods: ['GET', 'POST'])]
    public function request(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $limiter = $this->passwordResetLimiter->create($request->getClientIp());
            $limit = $limiter->consume(1);

            if (!$limit->isAccepted()) {
                return $this->json(
                    ['error' => 'Too many requests. Try again later.'],
                    Response::HTTP_TOO_MANY_REQUESTS,
                    ['Retry-After' => $limit->getRetryAfter()->getTimestamp() - time()]
                );
            }

            $csrfToken = $request->request->getString('_token');
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('password_reset_request', $csrfToken))) {
                $this->addFlash('error', 'Invalid security token. Please try again.');

                return $this->redirectToRoute('app_password_reset');
            }

            $this->passwordResetService->requestReset(
                $request->request->getString('email')
            );

            // Always show the same message (don't reveal whether email exists)
            $this->addFlash('success', 'If that email is registered, a password reset link has been sent.');

            return $this->redirectToRoute('app_password_reset');
        }

        return $this->render('auth/password_reset_request.html.twig');
    }

    #[Route('/password-reset/confirm', name: 'app_password_reset_confirm', methods: ['GET', 'POST'])]
    public function confirm(Request $request): Response
    {
        $rawToken = $request->query->getString('token');

        if ($request->isMethod('POST')) {
            $csrfToken = $request->request->getString('_token');
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('password_reset_confirm', $csrfToken))) {
                $this->addFlash('error', 'Invalid security token.');

                return $this->redirectToRoute('app_password_reset_confirm', ['token' => $rawToken]);
            }

            $result = $this->passwordResetService->resetPassword(
                $rawToken,
                $request->request->getString('password'),
            );

            if ($result['success']) {
                $this->addFlash('success', 'Password updated! You can now log in.');

                return $this->redirectToRoute('app_login');
            }

            $this->addFlash('error', $result['error'] ?? 'Could not reset password.');
        }

        return $this->render('auth/password_reset_confirm.html.twig', [
            'token' => $rawToken,
        ]);
    }
}
