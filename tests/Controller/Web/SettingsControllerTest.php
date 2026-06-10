<?php

declare(strict_types=1);

namespace App\Tests\Controller\Web;

use App\Controller\Web\SettingsController;
use App\Entity\Auth\User;
use App\Entity\Auth\VerificationToken;
use App\Enum\TokenType;
use App\Repository\Auth\UserRepository;
use App\Repository\Auth\VerificationTokenRepository;
use App\Repository\Notification\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorage;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class SettingsControllerTest extends KernelTestCase
{
    public function testIndexRedirectsToDashboard(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        
        $controller = $container->get(SettingsController::class);
        $response = $controller->index();
        
        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('/app/dashboard', $response->headers->get('location') ?? '');
        restore_exception_handler();
    }

    public function testChangeEmailValidatesCsrf(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $controller = $container->get(SettingsController::class);

        $request = new Request([], [], [], [], [], [], json_encode(['email' => 'new@example.com']));
        $request->headers->set('X-CSRF-Token', 'invalid');
        
        $session = new Session(new MockFileSessionStorage());
        $request->setSession($session);

        $requestStack = $container->get('request_stack');
        $requestStack->push($request);

        $em = $this->createMock(EntityManagerInterface::class);
        $userRepo = $this->createMock(UserRepository::class);
        $mailer = $this->createMock(MailerInterface::class);

        try {
            $response = $controller->changeEmail($request, $em, $userRepo, $mailer);
            $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        } finally {
            $requestStack->pop();
        }
        
        restore_exception_handler();
    }

    public function testConfirmEmailChangeOldInvalidToken(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $controller = $container->get(SettingsController::class);

        $request = new Request(['token' => 'nonexistent']);
        $tokenRepo = $this->createMock(VerificationTokenRepository::class);
        $tokenRepo->method('findActiveByToken')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $mailer = $this->createMock(MailerInterface::class);

        $session = new Session(new MockFileSessionStorage());
        $request->setSession($session);

        $requestStack = $container->get('request_stack');
        $requestStack->push($request);

        try {
            $response = $controller->confirmEmailChangeOld($request, $tokenRepo, $em, $mailer);
            $this->assertTrue($response->isRedirect());
            $this->assertCount(1, $session->getFlashBag()->get('error'));
        } finally {
            $requestStack->pop();
        }

        restore_exception_handler();
    }

    public function testConfirmCancelAccountInvalidToken(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $controller = $container->get(SettingsController::class);

        $request = new Request(['token' => 'invalid']);
        $tokenRepo = $this->createMock(VerificationTokenRepository::class);
        $tokenRepo->method('findActiveByToken')->willReturn(null);
        
        $notificationRepo = $this->createMock(NotificationRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $tokenStorage = $this->createMock(TokenStorageInterface::class);

        $session = new Session(new MockFileSessionStorage());
        $request->setSession($session);

        $requestStack = $container->get('request_stack');
        $requestStack->push($request);

        try {
            $response = $controller->confirmCancelAccount($request, $tokenRepo, $notificationRepo, $em, $tokenStorage);
            $this->assertTrue($response->isRedirect());
            $this->assertCount(1, $session->getFlashBag()->get('error'));
        } finally {
            $requestStack->pop();
        }

        restore_exception_handler();
    }
}
