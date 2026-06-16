<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Entity\Auth\User;
use App\Enum\FormationApproach;
use App\Service\Formation\FixtureFormationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/fixtures')]
class FixtureFormationController extends AbstractController
{
    public function __construct(
        private readonly FixtureFormationService $fixtureFormationService,
    ) {
    }

    #[Route('/{id}/formation', name: 'api_fixture_formation_get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function get(int $id): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->json(['error' => 'No team assigned to your account.'], 422);
        }

        $fixture = $this->fixtureFormationService->findFixtureForTeam($id, $team);
        if (null === $fixture) {
            return $this->json(['error' => 'Fixture not found.'], 404);
        }

        try {
            return $this->json($this->fixtureFormationService->getAssignmentState($fixture, $team));
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Assign formation for a fixture.
     *
     * Body modes:
     * - { "mode": "default" }
     * - { "mode": "saved", "formation_id": 123 }
     * - { "mode": "custom", "name": "...", "approach": "balanced", "slots": [...] }
     */
    #[Route('/{id}/formation', name: 'api_fixture_formation_save', methods: ['PUT'], requirements: ['id' => '\d+'])]
    public function save(int $id, Request $request): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->json(['error' => 'No team assigned to your account.'], 422);
        }

        $fixture = $this->fixtureFormationService->findFixtureForTeam($id, $team);
        if (null === $fixture) {
            return $this->json(['error' => 'Fixture not found.'], 404);
        }

        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent(), true) ?? [];
        $mode = (string) ($body['mode'] ?? '');

        try {
            if ('default' === $mode) {
                $this->fixtureFormationService->assignDefault($fixture, $team);
            } elseif ('saved' === $mode) {
                if (!isset($body['formation_id'])) {
                    return $this->json(['error' => 'Field "formation_id" is required for saved mode.'], 400);
                }
                $this->fixtureFormationService->assignSavedFormation($fixture, $team, (int) $body['formation_id']);
            } elseif ('custom' === $mode) {
                $name = (string) ($body['name'] ?? '');
                if ('' === trim($name)) {
                    return $this->json(['error' => 'Field "name" is required for custom mode.'], 400);
                }

                $approachValue = (string) ($body['approach'] ?? FormationApproach::Balanced->value);
                $approach = FormationApproach::tryFrom($approachValue);
                if (null === $approach) {
                    $valid = implode(', ', array_column(FormationApproach::cases(), 'value'));

                    return $this->json(['error' => sprintf('Invalid approach. Valid values: %s.', $valid)], 400);
                }

                /** @var list<array{position: string, hero_id: int|null, strategy: array, spell_priorities: array}> $slots */
                $slots = is_array($body['slots'] ?? null) ? $body['slots'] : [];
                $this->fixtureFormationService->saveMatchSpecificFormation($fixture, $team, $name, $approach, $slots);
            } else {
                return $this->json(['error' => 'Invalid mode. Use "default", "saved", or "custom".'], 400);
            }

            return $this->json($this->fixtureFormationService->getAssignmentState($fixture, $team));
        } catch (\DomainException|\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Promote the current match-specific formation to a saved player formation.
     *
     * Body: { "name": "My Formation", "is_default": false }
     */
    #[Route('/{id}/formation/promote', name: 'api_fixture_formation_promote', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function promote(int $id, Request $request): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->json(['error' => 'No team assigned to your account.'], 422);
        }

        $fixture = $this->fixtureFormationService->findFixtureForTeam($id, $team);
        if (null === $fixture) {
            return $this->json(['error' => 'Fixture not found.'], 404);
        }

        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent(), true) ?? [];
        $name = (string) ($body['name'] ?? '');
        if ('' === trim($name)) {
            return $this->json(['error' => 'Field "name" is required.'], 400);
        }

        $isDefault = (bool) ($body['is_default'] ?? false);

        try {
            $this->fixtureFormationService->promoteMatchFormation($fixture, $team, $name, $isDefault);

            return $this->json($this->fixtureFormationService->getAssignmentState($fixture, $team));
        } catch (\DomainException|\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }
    }

    private function getPlayerTeam(): ?\App\Entity\Team\Team
    {
        /** @var User|null $user */
        $user = $this->getUser();

        return $user?->getTeam();
    }
}
