<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Controller\Api\ApiControllerTrait;
use App\Entity\Auth\User;
use App\Service\Headquarters\ArenaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class ArenaController extends AbstractController
{
    use ApiControllerTrait;

    public function __construct(
        private readonly ArenaService $arenaService,
    ) {
    }

    #[Route('/api/v1/arena', name: 'api_arena_status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();
        if (!$team) {
            return $this->jsonError('error.no_team', 400);
        }

        return $this->json($this->arenaService->getArenaStatus($team));
    }

    #[Route('/api/v1/hq/arena/tickets/price', name: 'api_arena_update_ticket_price', methods: ['POST'])]
    #[Route('/api/v1/arena/tickets/price', name: 'api_arena_update_ticket_price_alias', methods: ['POST'])]
    public function updateTicketPrice(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();
        if (!$team) {
            return $this->jsonError('error.no_team', 400);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || !isset($data['ticket_price'])) {
            return $this->jsonError('arena.error_invalid_price', 400);
        }

        $price = (int) $data['ticket_price'];
        if ($price < 1 || $price > 50) {
            return $this->jsonError('arena.error_price_out_of_bounds', 400);
        }

        $status = $this->arenaService->updateTicketPrice($team, $price);

        return $this->json([
            'success' => true,
            'message' => 'arena.ticket_price_updated',
            'arena' => $status,
        ]);
    }
}
