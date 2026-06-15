<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Entity\Auth\User;
use App\Service\Arena\ArenaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class ArenaController extends AbstractController
{
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
            return $this->json(['error' => 'No team assigned.'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json($this->arenaService->getArenaStatus($team));
    }
}
