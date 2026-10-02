<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api\V1;

use App\Controller\Api\V1\ArenaController;
use App\Entity\Auth\User;
use App\Entity\Team\Team;
use App\Service\Headquarters\ArenaService;
use App\Service\Translation\UserMessageTranslator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use PHPUnit\Framework\MockObject\MockObject;

#[AllowMockObjectsWithoutExpectations]
class ArenaControllerTest extends TestCase
{
    /** @var ArenaService&MockObject */
    private $arenaService;
    private UserMessageTranslator $userMessages;
    private ArenaController $controller;
    private User $user;
    private Team $team;

    protected function setUp(): void
    {
        $this->arenaService = $this->createMock(ArenaService::class);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => $id);
        $this->userMessages = new UserMessageTranslator($translator, $this->createStub(RequestStack::class));

        $this->team = new Team();
        $this->team->setTicketPrice(5);

        $this->user = new User();
        $this->user->setTeam($this->team);

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($this->user);

        $tokenStorage = $this->createStub(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnCallback(static fn (string $id): bool => 'security.token_storage' === $id);
        $container->method('get')->willReturnCallback(static fn (string $id) => 'security.token_storage' === $id ? $tokenStorage : null);

        $this->controller = new ArenaController($this->arenaService);
        $this->controller->setContainer($container);
        $this->controller->setUserMessageTranslator($this->userMessages);
    }

    public function testStatusReturnsArenaData(): void
    {
        $this->arenaService
            ->expects($this->once())
            ->method('getArenaStatus')
            ->with($this->team)
            ->willReturn([
                'ticket_price' => 5,
                'seating_capacity' => 500,
                'elasticity_multiplier' => 1.0,
            ]);

        $response = $this->controller->status();
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        $this->assertSame(5, $data['ticket_price']);
    }

    public function testUpdateTicketPriceValidatesOutOfBounds(): void
    {
        $content = json_encode(['ticket_price' => 100]);
        $request = new Request([], [], [], [], [], [], false !== $content ? $content : null);

        $response = $this->controller->updateTicketPrice($request);
        $this->assertSame(400, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        $this->assertSame('arena.error_price_out_of_bounds', $data['error']);
    }

    public function testUpdateTicketPriceSuccess(): void
    {
        $content = json_encode(['ticket_price' => 12]);
        $request = new Request([], [], [], [], [], [], false !== $content ? $content : null);

        $this->arenaService
            ->expects($this->once())
            ->method('updateTicketPrice')
            ->with($this->team, 12)
            ->willReturn([
                'ticket_price' => 12,
                'elasticity_multiplier' => 0.52,
            ]);

        $response = $this->controller->updateTicketPrice($request);
        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode((string) $response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame(12, $data['arena']['ticket_price']);
    }
}
