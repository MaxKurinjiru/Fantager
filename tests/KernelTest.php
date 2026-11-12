<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class KernelTest extends KernelTestCase
{
    public function testCaughtApiOptimisticConflictReturnsConflict(): void
    {
        self::bootKernel();
        $controller = new ConflictTestController();
        $controller->setContainer(self::getContainer());
        $controller->setUserMessageTranslator(self::getContainer()->get(\App\Service\Translation\UserMessageTranslator::class));
        $response = $controller->respond(new \Doctrine\ORM\OptimisticLockException('stale', null));
        self::assertSame(409, $response->getStatusCode());
        restore_exception_handler();
    }

    public function testApiOptimisticConflictReturnsTranslatedConflict(): void
    {
        self::bootKernel();
        $request = \Symfony\Component\HttpFoundation\Request::create('/api/v1/marketplace/bid');
        $request->setLocale('en');
        $requestStack = self::getContainer()->get('request_stack');
        $requestStack->push($request);
        try {
            $kernel = self::$kernel;
            self::assertInstanceOf(\Symfony\Component\HttpKernel\HttpKernelInterface::class, $kernel);
            $event = new \Symfony\Component\HttpKernel\Event\ExceptionEvent(
                $kernel,
                $request,
                \Symfony\Component\HttpKernel\HttpKernelInterface::MAIN_REQUEST,
                new \Doctrine\ORM\OptimisticLockException('stale', null),
            );
            self::getContainer()->get('event_dispatcher')->dispatch($event, \Symfony\Component\HttpKernel\KernelEvents::EXCEPTION);
            $response = $event->getResponse();
            self::assertNotNull($response);
            self::assertSame(409, $response->getStatusCode());
            self::assertStringContainsString('Refresh and check', (string) $response->getContent());
        } finally {
            $requestStack->pop();
            restore_exception_handler();
        }
    }

    public function testKernelBoots(): void
    {
        self::bootKernel();
        $this->assertNotNull(self::$kernel);

        restore_exception_handler();
    }
}

final class ConflictTestController extends \Symfony\Bundle\FrameworkBundle\Controller\AbstractController
{
    use \App\Controller\Api\ApiControllerTrait;

    public function respond(\Throwable $exception): \Symfony\Component\HttpFoundation\JsonResponse
    {
        return $this->jsonException($exception, 422);
    }
}
