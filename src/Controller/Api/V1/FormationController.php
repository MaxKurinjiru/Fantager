<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Entity\Auth\User;
use App\Enum\FormationApproach;
use App\Service\Formation\FormationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/formations')]
class FormationController extends AbstractController
{
    public function __construct(
        private readonly FormationService $formationService,
    ) {
    }

    #[Route('', name: 'api_formations_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->json(['error' => 'No team assigned to your account.'], 422);
        }

        $formations = $this->formationService->listByTeam($team);

        return $this->json(array_map($this->formationService->serialize(...), $formations));
    }

    /**
     * Create or update a formation (upsert by id).
     *
     * Body:
     * {
     *   "id": null,            // null to create new
     *   "name": "My Formation",
     *   "approach": "balanced",
     *   "is_default": true,
     *   "slots": [
     *     { "position": "front_1", "hero_id": 5, "strategy": {}, "spell_priorities": [] },
     *     ...
     *   ]
     * }
     */
    #[Route('', name: 'api_formations_save', methods: ['PUT'])]
    public function save(Request $request): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->json(['error' => 'No team assigned to your account.'], 422);
        }

        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent(), true) ?? [];

        $name = (string) ($body['name'] ?? '');
        if ('' === $name) {
            return $this->json(['error' => 'Field "name" is required.'], 400);
        }

        $approachValue = (string) ($body['approach'] ?? FormationApproach::Balanced->value);
        $approach = FormationApproach::tryFrom($approachValue);
        if (null === $approach) {
            $valid = implode(', ', array_column(FormationApproach::cases(), 'value'));

            return $this->json(['error' => sprintf('Invalid approach. Valid values: %s.', $valid)], 400);
        }

        $id = isset($body['id']) ? (int) $body['id'] : null;
        $isDefault = (bool) ($body['is_default'] ?? false);

        /** @var list<array{position: string, hero_id: int|null, strategy: array, spell_priorities: array}> $slots */
        $slots = is_array($body['slots'] ?? null) ? $body['slots'] : [];

        try {
            $formation = $this->formationService->save($team, $id, $name, $approach, $slots, $isDefault);
        } catch (\DomainException|\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }

        return $this->json($this->formationService->serialize($formation), null === $id ? 201 : 200);
    }

    #[Route('/{id}', name: 'api_formations_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $id): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->json(['error' => 'No team assigned to your account.'], 422);
        }

        $formation = $this->formationService->findForTeam($id, $team);
        if (null === $formation) {
            return $this->json(['error' => 'Formation not found.'], 404);
        }

        try {
            $this->formationService->delete($formation, $team);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }

        return $this->json(['message' => 'Formation deleted.']);
    }

    private function getPlayerTeam(): ?\App\Entity\Team\Team
    {
        /** @var User|null $user */
        $user = $this->getUser();

        return $user?->getTeam();
    }
}
