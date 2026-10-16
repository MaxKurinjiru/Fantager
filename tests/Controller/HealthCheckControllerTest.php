<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\HealthCheckController;
use PHPUnit\Framework\TestCase;

class HealthCheckControllerTest extends TestCase
{
    public function testHealthReturnsOkWithoutSecrets(): void
    {
        $controller = new HealthCheckController();
        $response = $controller->health();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(['status' => 'ok'], $payload);
    }
}
