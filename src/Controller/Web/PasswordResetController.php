<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Service\Auth\PasswordResetService;
use App\Service\Translation\UserMessageTranslator;
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
        private readonly UserMessageTranslator $userMessages,
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
                    ['error' => $this->userMessages->trans('flash.too_many_requests')],
                    Response::HTTP_TOO_MANY_REQUESTS,
                    ['Retry-After' => $limit->getRetryAfter()->getTimestamp() - time()]
                );
            }

            $csrfToken = $request->request->getString('_token');
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('password_reset_request', $csrfToken))) {
                $this->addFlash('error', $this->userMessages->trans('flash.invalid_csrf'));

                return $this->redirectToRoute('app_password_reset');
            }

            $this->passwordResetService->requestReset(
                $request->request->getString('email')
            );

            // Always show the same message (don't reveal whether email exists)
            $this->addFlash('success', $this->userMessages->trans('flash.password_reset_sent'));

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
                $this->addFlash('error', $this->userMessages->trans('error.invalid_csrf'));

                return $this->redirectToRoute('app_password_reset_confirm', ['token' => $rawToken]);
            }

            $result = $this->passwordResetService->resetPassword(
                $rawToken,
                $request->request->getString('password'),
            );

            if ($result['success']) {
                $this->addFlash('success', $this->userMessages->trans('flash.password_updated'));

                return $this->redirectToRoute('app_login');
            }

            $this->addFlash('error', $result['error'] ?? $this->userMessages->trans('flash.password_reset_failed'));
        }

        return $this->render('auth/password_reset_confirm.html.twig', [
            'token' => $rawToken,
        ]);
    }
}
