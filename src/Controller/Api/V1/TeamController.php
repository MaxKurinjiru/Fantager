<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Controller\Api\ApiControllerTrait;
use App\Entity\Auth\User;
use App\Service\Team\TeamService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/teams/{teamId}', requirements: ['teamId' => '\d+'])]
class TeamController extends AbstractController
{
    use ApiControllerTrait;

    public function __construct(
        private readonly TeamService $teamService,
    ) {
    }

    #[Route('/dashboard', name: 'api_team_dashboard', methods: ['GET'])]
    public function dashboard(int $teamId): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->jsonError('error.no_team', 422);
        }

        if ($team->getId() !== $teamId) {
            return $this->jsonError('error.access_denied', 403);
        }

        return $this->json($this->teamService->getDashboardData($team));
    }

    #[Route('/settings', name: 'api_team_settings', methods: ['POST'])]
    public function settings(int $teamId, Request $request): JsonResponse
    {
        $csrfToken = $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('api', $csrfToken)) {
            return $this->jsonError('error.invalid_csrf', 403);
        }

        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->jsonError('error.no_team', 422);
        }

        if ($team->getId() !== $teamId) {
            return $this->jsonError('error.access_denied', 403);
        }

        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent(), true) ?? [];

        try {
            $this->teamService->updateSettings($team, $body);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonException($e, 400);
        }

        return $this->json([
            'id' => $team->getId(),
            'name' => $team->getName(),
            'emblem' => $team->getEmblem(),
            'colors' => $team->getColors(),
        ]);
    }

    private function getPlayerTeam(): ?\App\Entity\Team\Team
    {
        /** @var User|null $user */
        $user = $this->getUser();

        return $user?->getTeam();
    }
}
