<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\HealthCheckController;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

class HealthCheckControllerTest extends KernelTestCase
{
    public function testHealthReturnsOkWithoutSecrets(): void
    {
        $controller = new HealthCheckController();
        $response = $controller->health();

        $this->assertHealthLivenessResponse($response);
    }

    public function testHealthHttpGoesThroughKernelWithoutSession(): void
    {
        self::bootKernel();

        $kernel = self::$kernel;
        $this->assertNotNull($kernel);

        $request = Request::create('/health', 'GET');
        $response = $kernel->handle($request);

        try {
            $this->assertHealthLivenessResponse($response);

            foreach ($response->headers->getCookies() as $cookie) {
                $this->assertFalse(
                    \in_array($cookie->getName(), ['PHPSESSID', 'MOCKSESSID'], true),
                    sprintf('Health must not start a session cookie (%s).', $cookie->getName()),
                );
            }
        } finally {
            $kernel->terminate($request, $response);
            restore_exception_handler();
        }
    }

    public function testHealthRouteIsRegistered(): void
    {
        self::bootKernel();

        /** @var RouterInterface $router */
        $router = self::getContainer()->get('router');
        $route = $router->getRouteCollection()->get('app_health');

        $this->assertNotNull($route);
        $this->assertSame('/health', $route->getPath());
        $this->assertSame(['GET'], $route->getMethods());

        restore_exception_handler();
    }

    private function assertHealthLivenessResponse(Response $response): void
    {
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(['status' => 'ok'], $payload);
    }
}
