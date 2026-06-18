<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use App\Exception\UserFacingException;
use App\Service\Auth\AccountSettingsService;
use App\Service\Auth\UserSettingsService;
use App\Service\Translation\UserMessageTranslator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class SettingsController extends AbstractController
{
    public function __construct(
        private readonly UserMessageTranslator $userMessages,
        private readonly AccountSettingsService $accountSettingsService,
        private readonly UserSettingsService $userSettingsService,
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
    public function changeEmail(Request $request): JsonResponse
    {
        $csrfToken = $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('api', $csrfToken)) {
            return $this->json(['error' => $this->userMessages->trans('error.invalid_csrf')], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $newEmail = strtolower(trim($data['email'] ?? ''));

        /** @var User $user */
        $user = $this->getUser();

        try {
            $this->accountSettingsService->requestEmailChange($user, $newEmail);
        } catch (UserFacingException $e) {
            return $this->json(['error' => $this->userMessages->fromException($e)], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['message' => $this->userMessages->trans('flash.email_change_link_sent')]);
    }

    #[Route('/confirm-email-change/old', name: 'app_confirm_email_change_old', methods: ['GET'])]
    public function confirmEmailChangeOld(Request $request): Response
    {
        try {
            $this->accountSettingsService->confirmEmailChangeFromOldToken($request->query->getString('token'));
        } catch (UserFacingException $e) {
            $this->addFlash('error', $this->userMessages->fromException($e));

            return $this->redirectToRoute('app_home');
        }

        $this->addFlash('success', $this->userMessages->trans('flash.email_confirm_old_sent'));

        return $this->redirectToRoute('app_home');
    }

    #[Route('/confirm-email-change/new', name: 'app_confirm_email_change_new', methods: ['GET'])]
    public function confirmEmailChangeNew(Request $request): Response
    {
        try {
            $this->accountSettingsService->confirmEmailChangeFromNewToken($request->query->getString('token'));
        } catch (UserFacingException $e) {
            $this->addFlash('error', $this->userMessages->fromException($e));

            return $this->redirectToRoute('app_home');
        }

        $this->addFlash('success', $this->userMessages->trans('flash.email_updated'));

        return $this->redirectToRoute('app_home');
    }

    #[Route('/app/settings/preferences', name: 'api_update_preferences', methods: ['POST'])]
    #[IsGranted('ROLE_PLAYER')]
    public function updatePreferences(Request $request): JsonResponse
    {
        $csrfToken = $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('api', $csrfToken)) {
            return $this->json(['error' => $this->userMessages->trans('error.invalid_csrf')], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return $this->json(['error' => $this->userMessages->trans('error.invalid_request_payload')], Response::HTTP_BAD_REQUEST);
        }

        if (!\array_key_exists('closeModalOnBackdrop', $data)) {
            return $this->json(['error' => $this->userMessages->trans('error.no_preferences_provided')], Response::HTTP_BAD_REQUEST);
        }

        if (!\is_bool($data['closeModalOnBackdrop'])) {
            return $this->json(['error' => $this->userMessages->trans('error.invalid_close_modal_on_backdrop')], Response::HTTP_BAD_REQUEST);
        }

        /** @var User $user */
        $user = $this->getUser();
        $settings = $this->userSettingsService->updateCloseModalOnBackdrop($user, $data['closeModalOnBackdrop']);

        return $this->json([
            'message' => $this->userMessages->trans('flash.preferences_saved'),
            'closeModalOnBackdrop' => $settings->isCloseModalOnBackdrop(),
        ]);
    }

    #[Route('/app/settings/cancel-account', name: 'api_cancel_account', methods: ['POST'])]
    #[IsGranted('ROLE_PLAYER')]
    public function cancelAccount(Request $request): JsonResponse
    {
        $csrfToken = $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('api', $csrfToken)) {
            return $this->json(['error' => $this->userMessages->trans('error.invalid_csrf')], Response::HTTP_FORBIDDEN);
        }

        /** @var User $user */
        $user = $this->getUser();
        $this->accountSettingsService->requestAccountDeletion($user);

        return $this->json(['message' => $this->userMessages->trans('flash.account_deletion_link_sent')]);
    }

    #[Route('/confirm-cancel-account', name: 'app_confirm_cancel_account', methods: ['GET'])]
    public function confirmCancelAccount(
        Request $request,
        TokenStorageInterface $tokenStorage,
    ): Response {
        try {
            $this->accountSettingsService->confirmAccountDeletion($request->query->getString('token'));
        } catch (UserFacingException $e) {
            $this->addFlash('error', $this->userMessages->fromException($e));

            return $this->redirectToRoute('app_home');
        }

        $request->getSession()->invalidate();
        $tokenStorage->setToken(null);

        $this->addFlash('success', $this->userMessages->trans('flash.account_deleted'));

        return $this->redirectToRoute('app_home');
    }
}
