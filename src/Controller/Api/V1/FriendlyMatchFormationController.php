<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Controller\Api\ApiControllerTrait;
use App\Entity\Auth\User;
use App\Entity\Team\Team;
use App\Repository\FriendlyMatch\FriendlyMatchRepository;
use App\Service\FriendlyMatch\FriendlyMatchService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Formation assignment for friendly matches.
 *
 * Supports only "default" (null) and "saved" (formation_id) modes —
 * friendly matches do not use temporary match-specific formations.
 */
#[IsGranted('ROLE_PLAYER')]
#[Route('/api/v1/friendly-matches')]
class FriendlyMatchFormationController extends AbstractController
{
    use ApiControllerTrait;

    public function __construct(
        private readonly FriendlyMatchRepository $friendlyMatchRepository,
        private readonly FriendlyMatchService $friendlyMatchService,
    ) {
    }

    /**
     * GET /api/v1/friendly-matches/{id}/formation.
     *
     * Returns current formation assignment for the requesting team.
     */
    #[Route('/{id}/formation', name: 'api_friendly_formation_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function get(int $id): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->jsonError('error.no_team', 422);
        }

        $match = $this->friendlyMatchRepository->find($id);
        if (null === $match) {
            return $this->jsonError('error.friendly_not_found', 404);
        }

        if (!$match->isParticipant($team)) {
            return $this->jsonError('error.friendly_not_participant', 403);
        }

        try {
            return $this->json($this->friendlyMatchService->getFormationState($match, $team));
        } catch (\DomainException $e) {
            return $this->jsonException($e, 422);
        }
    }

    /**
     * PUT /api/v1/friendly-matches/{id}/formation.
     *
     * Body modes:
     * - { "mode": "default" }
     * - { "mode": "saved", "formation_id": 123 }
     */
    #[Route('/{id}/formation', name: 'api_friendly_formation_save', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function save(int $id, Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('api', $request->headers->get('X-CSRF-Token'))) {
            return $this->jsonError('error.invalid_csrf', 403);
        }

        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->jsonError('error.no_team', 422);
        }

        $match = $this->friendlyMatchRepository->find($id);
        if (null === $match) {
            return $this->jsonError('error.friendly_not_found', 404);
        }

        if (!$match->isParticipant($team)) {
            return $this->jsonError('error.friendly_not_participant', 403);
        }

        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent(), true) ?? [];
        $mode = (string) ($body['mode'] ?? '');

        try {
            if ('default' === $mode) {
                $this->friendlyMatchService->assignDefault($match, $team);
            } elseif ('saved' === $mode) {
                if (!isset($body['formation_id'])) {
                    return $this->jsonError('error.field_formation_id_required', 400);
                }
                $this->friendlyMatchService->assignSavedFormation($match, $team, (int) $body['formation_id']);
            } else {
                return $this->jsonError('error.invalid_mode', 400);
            }

            return $this->json($this->friendlyMatchService->getFormationState($match, $team));
        } catch (\DomainException|\InvalidArgumentException $e) {
            return $this->jsonException($e, 422);
        }
    }

    private function getPlayerTeam(): ?Team
    {
        /** @var User|null $user */
        $user = $this->getUser();

        return $user?->getTeam();
    }
}
