<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Controller\Api\ApiControllerTrait;
use App\Entity\Auth\User;
use App\Entity\Team\Team;
use App\Enum\FriendlyMatchStatus;
use App\Repository\FriendlyMatch\FriendlyMatchRepository;
use App\Repository\Team\TeamRepository;
use App\Service\FriendlyMatch\FriendlyMatchService;
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
        private readonly FriendlyMatchService $friendlyMatchService,
        private readonly FriendlyMatchRepository $friendlyMatchRepository,
        private readonly TeamRepository $teamRepository,
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

    // -------------------------------------------------------------------------
    // Friendly match endpoints
    // -------------------------------------------------------------------------

    /**
     * GET /api/v1/arena/friendly/slots?opponent_team_id=123.
     *
     * Returns available scheduledAt values for a new friendly invite.
     */
    #[Route('/api/v1/arena/friendly/slots', name: 'api_arena_friendly_slots', methods: ['GET'])]
    public function friendlySlots(Request $request): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->jsonError('error.no_team', 400);
        }

        $opponentId = (int) $request->query->get('opponent_team_id', 0);
        if ($opponentId <= 0) {
            return $this->jsonError('error.invalid_request_payload', 400);
        }

        $opponent = $this->teamRepository->find($opponentId);
        if (null === $opponent) {
            return $this->jsonError('error.no_team', 404);
        }

        try {
            $slots = $this->friendlyMatchService->listAvailableSlots(
                $team,
                $opponent,
                new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
            );

            return $this->json(['slots' => $slots]);
        } catch (\DomainException $e) {
            return $this->jsonException($e, 422);
        }
    }

    /**
     * POST /api/v1/arena/schedule-match.
     *
     * Body: { "opponent_team_id": 123, "scheduled_at": "2026-10-21T16:00:00+00:00" }
     */
    #[Route('/api/v1/arena/schedule-match', name: 'api_arena_schedule_friendly', methods: ['POST'])]
    public function scheduleFriendly(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('api', $request->headers->get('X-CSRF-Token'))) {
            return $this->jsonError('error.invalid_csrf', 403);
        }

        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->jsonError('error.no_team', 400);
        }

        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent(), true) ?? [];
        $opponentId = (int) ($body['opponent_team_id'] ?? 0);
        $scheduledAtRaw = (string) ($body['scheduled_at'] ?? '');

        if ($opponentId <= 0 || '' === $scheduledAtRaw) {
            return $this->jsonError('error.invalid_request_payload', 400);
        }

        $opponent = $this->teamRepository->find($opponentId);
        if (null === $opponent) {
            return $this->jsonError('error.no_team', 404);
        }

        try {
            $scheduledAt = new \DateTimeImmutable($scheduledAtRaw, new \DateTimeZone('UTC'));
        } catch (\Exception) {
            return $this->jsonError('error.invalid_date_format', 400);
        }

        try {
            $match = $this->friendlyMatchService->createInvite(
                $team,
                $opponent,
                $scheduledAt,
                new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
            );

            return $this->json($this->friendlyMatchService->serialize($match), 201);
        } catch (\Throwable $e) {
            return $this->jsonException($e, 422);
        }
    }

    /**
     * POST /api/v1/arena/friendly/{id}/accept.
     */
    #[Route('/api/v1/arena/friendly/{id}/accept', name: 'api_arena_friendly_accept', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function acceptFriendly(int $id, Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('api', $request->headers->get('X-CSRF-Token'))) {
            return $this->jsonError('error.invalid_csrf', 403);
        }

        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->jsonError('error.no_team', 400);
        }

        $match = $this->friendlyMatchRepository->find($id);
        if (null === $match) {
            return $this->jsonError('error.friendly_not_found', 404);
        }

        try {
            $this->friendlyMatchService->accept($match, $team, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

            return $this->json($this->friendlyMatchService->serialize($match));
        } catch (\Throwable $e) {
            return $this->jsonException($e, 422);
        }
    }

    /**
     * POST /api/v1/arena/friendly/{id}/decline.
     */
    #[Route('/api/v1/arena/friendly/{id}/decline', name: 'api_arena_friendly_decline', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function declineFriendly(int $id, Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('api', $request->headers->get('X-CSRF-Token'))) {
            return $this->jsonError('error.invalid_csrf', 403);
        }

        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->jsonError('error.no_team', 400);
        }

        $match = $this->friendlyMatchRepository->find($id);
        if (null === $match) {
            return $this->jsonError('error.friendly_not_found', 404);
        }

        try {
            $this->friendlyMatchService->decline($match, $team);

            return $this->json($this->friendlyMatchService->serialize($match));
        } catch (\Throwable $e) {
            return $this->jsonException($e, 422);
        }
    }

    /**
     * POST /api/v1/arena/friendly/{id}/cancel.
     */
    #[Route('/api/v1/arena/friendly/{id}/cancel', name: 'api_arena_friendly_cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function cancelFriendly(int $id, Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('api', $request->headers->get('X-CSRF-Token'))) {
            return $this->jsonError('error.invalid_csrf', 403);
        }

        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->jsonError('error.no_team', 400);
        }

        $match = $this->friendlyMatchRepository->find($id);
        if (null === $match) {
            return $this->jsonError('error.friendly_not_found', 404);
        }

        try {
            $this->friendlyMatchService->cancel($match, $team, new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

            return $this->json($this->friendlyMatchService->serialize($match));
        } catch (\Throwable $e) {
            return $this->jsonException($e, 422);
        }
    }

    /**
     * GET /api/v1/arena/friendly?status=pending.
     *
     * List kingdom friendly matches with optional status filter.
     */
    #[Route('/api/v1/arena/friendly', name: 'api_arena_friendly_list', methods: ['GET'])]
    public function listFriendly(Request $request): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->jsonError('error.no_team', 400);
        }

        $statusRaw = $request->query->get('status');
        $status = null;
        if (null !== $statusRaw) {
            $status = FriendlyMatchStatus::tryFrom((string) $statusRaw);
            if (null === $status) {
                return $this->jsonError('error.invalid_request_payload', 400);
            }
        }

        $matches = $this->friendlyMatchService->listForKingdom($team->getKingdom(), $status);

        return $this->json(['friendly_matches' => $this->friendlyMatchService->serializeList($matches)]);
    }

    private function getPlayerTeam(): ?Team
    {
        /** @var User|null $user */
        $user = $this->getUser();

        return $user?->getTeam();
    }
}
