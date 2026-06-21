<?php

declare(strict_types=1);

namespace App\Tests\Controller\Web;

use App\Controller\Web\SettingsController;
use App\Exception\UserFacingException;
use App\Service\Auth\AccountSettingsService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class SettingsControllerTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        restore_exception_handler();
        parent::tearDown();
    }

    public function testIndexRedirectsToDashboard(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var SettingsController $controller */
        $controller = $container->get(SettingsController::class);
        $response = $controller->index();

        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('/app/dashboard', $response->headers->get('location') ?? '');
    }

    public function testChangeEmailValidatesCsrf(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        /** @var SettingsController $controller */
        $controller = $container->get(SettingsController::class);

        $request = new Request([], [], [], [], [], [], (string) json_encode(['email' => 'new@example.com']));
        $request->headers->set('X-CSRF-Token', 'invalid');

        $session = new Session(new MockFileSessionStorage());
        $request->setSession($session);

        /** @var \Symfony\Component\HttpFoundation\RequestStack $requestStack */
        $requestStack = $container->get('request_stack');
        $requestStack->push($request);

        try {
            $response = $controller->changeEmail($request);
            $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        } finally {
            $requestStack->pop();
        }
    }

    public function testConfirmEmailChangeOldInvalidToken(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $accountSettingsService = $this->createMock(AccountSettingsService::class);
        $accountSettingsService
            ->method('confirmEmailChangeFromOldToken')
            ->willThrowException(new UserFacingException('error.invalid_email_confirmation_link'));
        $container->set(AccountSettingsService::class, $accountSettingsService);

        /** @var SettingsController $controller */
        $controller = $container->get(SettingsController::class);

        $request = new Request(['token' => 'nonexistent']);

        $session = new Session(new MockFileSessionStorage());
        $request->setSession($session);

        /** @var \Symfony\Component\HttpFoundation\RequestStack $requestStack */
        $requestStack = $container->get('request_stack');
        $requestStack->push($request);

        try {
            $response = $controller->confirmEmailChangeOld($request);
            $this->assertTrue($response->isRedirect());
            $this->assertCount(1, $session->getFlashBag()->get('error'));
        } finally {
            $requestStack->pop();
        }
    }

    public function testUpdatePreferencesValidatesCsrf(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        /** @var SettingsController $controller */
        $controller = $container->get(SettingsController::class);

        $request = new Request([], [], [], [], [], [], (string) json_encode(['closeModalOnBackdrop' => true]));
        $request->headers->set('X-CSRF-Token', 'invalid');

        $session = new Session(new MockFileSessionStorage());
        $request->setSession($session);

        /** @var \Symfony\Component\HttpFoundation\RequestStack $requestStack */
        $requestStack = $container->get('request_stack');
        $requestStack->push($request);

        try {
            $response = $controller->updatePreferences($request);
            $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        } finally {
            $requestStack->pop();
        }
    }

    public function testUpdatePreferencesRejectsMissingValue(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        /** @var SettingsController $controller */
        $controller = $container->get(SettingsController::class);

        $session = new Session(new MockFileSessionStorage());
        $request = new Request([], [], [], [], [], [], (string) json_encode([]));
        $request->setSession($session);

        /** @var \Symfony\Component\HttpFoundation\RequestStack $requestStack */
        $requestStack = $container->get('request_stack');
        $requestStack->push($request);

        /** @var \Symfony\Component\Security\Csrf\CsrfTokenManagerInterface $tokenManager */
        $tokenManager = $container->get('security.csrf.token_manager');
        $csrfToken = $tokenManager->getToken('api')->getValue();
        $request->headers->set('X-CSRF-Token', $csrfToken);

        try {
            $response = $controller->updatePreferences($request);
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        } finally {
            $requestStack->pop();
        }
    }

    public function testUpdatePreferencesRejectsNonBooleanValue(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        /** @var SettingsController $controller */
        $controller = $container->get(SettingsController::class);

        $session = new Session(new MockFileSessionStorage());
        $request = new Request([], [], [], [], [], [], (string) json_encode(['closeModalOnBackdrop' => 'yes']));
        $request->setSession($session);

        /** @var \Symfony\Component\HttpFoundation\RequestStack $requestStack */
        $requestStack = $container->get('request_stack');
        $requestStack->push($request);

        /** @var \Symfony\Component\Security\Csrf\CsrfTokenManagerInterface $tokenManager */
        $tokenManager = $container->get('security.csrf.token_manager');
        $csrfToken = $tokenManager->getToken('api')->getValue();
        $request->headers->set('X-CSRF-Token', $csrfToken);

        try {
            $response = $controller->updatePreferences($request);
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        } finally {
            $requestStack->pop();
        }
    }

    public function testConfirmCancelAccountInvalidToken(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $accountSettingsService = $this->createMock(AccountSettingsService::class);
        $accountSettingsService
            ->method('confirmAccountDeletion')
            ->willThrowException(new UserFacingException('error.invalid_account_cancellation_link'));
        $container->set(AccountSettingsService::class, $accountSettingsService);

        /** @var SettingsController $controller */
        $controller = $container->get(SettingsController::class);

        $request = new Request(['token' => 'invalid']);
        $tokenStorage = $this->createMock(TokenStorageInterface::class);

        $session = new Session(new MockFileSessionStorage());
        $request->setSession($session);

        /** @var \Symfony\Component\HttpFoundation\RequestStack $requestStack */
        $requestStack = $container->get('request_stack');
        $requestStack->push($request);

        try {
            $response = $controller->confirmCancelAccount($request, $tokenStorage);
            $this->assertTrue($response->isRedirect());
            $this->assertCount(1, $session->getFlashBag()->get('error'));
        } finally {
            $requestStack->pop();
        }
    }
}
