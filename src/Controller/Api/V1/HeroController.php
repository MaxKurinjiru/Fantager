<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Entity\Auth\User;
use App\Service\Hero\HeroDismissalService;
use App\Service\Hero\HeroService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/heroes')]
class HeroController extends AbstractController
{
    public function __construct(
        private readonly HeroService $heroService,
        private readonly HeroDismissalService $heroDismissalService,
    ) {
    }

    #[Route('', name: 'api_heroes_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->json(['error' => 'No team assigned to your account.'], 422);
        }

        $heroes = $this->heroService->listByTeam($team);

        return $this->json(array_map($this->heroService->serialize(...), $heroes));
    }

    #[Route('/{id}', name: 'api_heroes_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function detail(int $id): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->json(['error' => 'No team assigned to your account.'], 422);
        }

        $hero = $this->heroService->findForTeam($id, $team);
        if (null === $hero) {
            return $this->json(['error' => 'Hero not found.'], 404);
        }

        return $this->json($this->heroService->serialize($hero));
    }

    #[Route('/{id}', name: 'api_heroes_update', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->json(['error' => 'No team assigned to your account.'], 422);
        }

        $hero = $this->heroService->findForTeam($id, $team);
        if (null === $hero) {
            return $this->json(['error' => 'Hero not found.'], 404);
        }

        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent(), true) ?? [];

        if (isset($body['name'])) {
            try {
                $this->heroService->rename($hero, (string) $body['name']);
            } catch (\InvalidArgumentException $e) {
                return $this->json(['error' => $e->getMessage()], 400);
            }
        }

        return $this->json($this->heroService->serialize($hero));
    }

    #[Route('/{id}/dismiss', name: 'api_heroes_dismiss', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function dismiss(int $id): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->json(['error' => 'No team assigned to your account.'], 422);
        }

        $hero = $this->heroService->findForTeam($id, $team);
        if (null === $hero) {
            return $this->json(['error' => 'Hero not found.'], 404);
        }

        try {
            $compensation = $this->heroDismissalService->dismiss($team, $hero);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }

        return $this->json([
            'dismissed' => true,
            'compensation' => $compensation,
        ]);
    }

    private function getPlayerTeam(): ?\App\Entity\Team\Team
    {
        /** @var User|null $user */
        $user = $this->getUser();

        return $user?->getTeam();
    }
}
