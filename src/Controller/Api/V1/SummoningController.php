<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Entity\Auth\User;
use App\Service\Hero\HeroService;
use App\Service\Summoning\SummoningService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/summoning')]
class SummoningController extends AbstractController
{
    public function __construct(
        private readonly SummoningService $summoningService,
        private readonly HeroService $heroService,
    ) {
    }

    #[Route('/status', name: 'api_summoning_status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->json(['error' => 'No team assigned to your account.'], 422);
        }

        return $this->json($this->summoningService->getStatus($team));
    }

    #[Route('', name: 'api_summoning_summon', methods: ['POST'])]
    public function summon(Request $request): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->json(['error' => 'No team assigned to your account.'], 422);
        }

        try {
            $hero = $this->summoningService->summon($team);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }

        return $this->json($this->heroService->serialize($hero), 201);
    }

    private function getPlayerTeam(): ?\App\Entity\Team\Team
    {
        /** @var User|null $user */
        $user = $this->getUser();

        return $user?->getTeam();
    }
}
