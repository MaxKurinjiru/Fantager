<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Controller\Api\ApiControllerTrait;
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
    use ApiControllerTrait;

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
            return $this->jsonError('error.no_team', 422);
        }

        $status = $this->summoningService->getStatus($team);
        $reason = $status['reason'];
        if (null !== $reason) {
            $params = $status['reason_parameters'] ?? [];
            if ('error.summoning_insufficient_gold' === $reason) {
                $params = ['%required%' => $status['gold_cost'], '%available%' => $team->getGold()];
            }
            $reason = $this->transMessage($reason, $params);
        }

        return $this->json([
            'available' => $status['available'],
            'reason' => $reason,
            'gold_cost' => $status['gold_cost'],
            'summons_used' => $status['summons_used'],
            'summons_max' => $status['summons_max'],
        ]);
    }

    #[Route('', name: 'api_summoning_summon', methods: ['POST'])]
    public function summon(Request $request): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->jsonError('error.no_team', 422);
        }

        try {
            $hero = $this->summoningService->summon($team);
        } catch (\DomainException $e) {
            return $this->jsonException($e, 422);
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
