<?php

declare(strict_types=1);

namespace App\Tests\Controller\Web;

use App\Controller\Web\LocaleController;
use App\Entity\Auth\User;
use App\EventListener\LocaleSubscriber;
use App\EventListener\UserLocaleListener;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class LocaleControllerTest extends KernelTestCase
{
    public function testChangeLocaleGuest(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        
        $controller = $container->get(LocaleController::class);
        
        $session = new Session(new MockFileSessionStorage());
        $request = new Request();
        $request->setSession($session);
        
        $em = $this->createMock(EntityManagerInterface::class);
        
        $response = $controller->changeLocale('en', $request, $em);
        
        $this->assertTrue($response->isRedirect());
        $this->assertEquals('en', $session->get('_locale'));
        
        restore_exception_handler();
    }

    public function testLocaleSubscriberSetsRequestLocale(): void
    {
        $subscriber = new LocaleSubscriber('cs');
        
        $session = new Session(new MockFileSessionStorage());
        $session->set('_locale', 'en');
        
        $request = new Request();
        $request->setSession($session);
        
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        
        $subscriber->onKernelRequest($event);
        
        $this->assertEquals('en', $request->getLocale());
    }

    public function testUserLocaleListenerSetsSessionOnLogin(): void
    {
        $listener = new UserLocaleListener();
        
        $user = new User();
        $user->setLocale('en');
        $user->setEmail('test@example.com');
        $user->setDisplayName('TestUser');
        
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        
        $session = new Session(new MockFileSessionStorage());
        $request = new Request();
        $request->setSession($session);
        
        $event = new InteractiveLoginEvent($request, $token);
        
        $listener($event);
        
        $this->assertEquals('en', $session->get('_locale'));
    }
}
