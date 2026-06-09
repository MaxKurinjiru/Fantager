<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Entity\Auth\User;
use App\Enum\FacilityType;
use App\Service\Headquarters\HeadquartersService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/hq')]
class HeadquartersController extends AbstractController
{
    public function __construct(
        private readonly HeadquartersService $hqService,
    ) {
    }

    #[Route('', name: 'api_hq_get', methods: ['GET'])]
    public function get(): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->json(['error' => 'No team assigned to your account.'], 422);
        }

        try {
            $hq = $this->hqService->getForTeam($team);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], 404);
        }

        $facilities = [];
        foreach ($hq->getFacilities() as $facility) {
            $facilities[] = $this->hqService->serializeFacility($facility, $facility->getType());
        }

        return $this->json([
            'total_level' => $hq->getTotalLevel(),
            'race_optimization' => $hq->getRaceOptimization(),
            'pending_race_optimization' => $hq->getPendingRaceOptimization(),
            'is_optimization_locked' => ($hq->hasPendingRaceOptimizationChange() || $hq->isRaceOptimizationLockCycle()),
            'facilities' => $facilities,
        ]);
    }

    #[Route('/upgrade', name: 'api_hq_upgrade', methods: ['POST'])]
    public function upgrade(Request $request): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->json(['error' => 'No team assigned to your account.'], 422);
        }

        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent(), true) ?? [];

        $facilityValue = (string) ($body['facility'] ?? '');
        $type = FacilityType::tryFrom($facilityValue);
        if (null === $type) {
            $valid = implode(', ', array_column(FacilityType::cases(), 'value'));

            return $this->json(['error' => sprintf('Invalid facility type. Valid values: %s.', $valid)], 400);
        }

        try {
            $facility = $this->hqService->upgradeFacility($team, $type);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }

        return $this->json($this->hqService->serializeFacility($facility, $type));
    }

    #[Route('/optimize', name: 'api_hq_optimize', methods: ['POST'])]
    public function optimize(Request $request): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->json(['error' => 'No team assigned to your account.'], 422);
        }

        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent(), true) ?? [];
        $raceValue = isset($body['race']) && '' !== $body['race'] ? (string) $body['race'] : null;

        try {
            $hq = $this->hqService->updateRaceOptimization($team, $raceValue);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }

        return $this->json([
            'race_optimization' => $hq->getRaceOptimization(),
            'pending_race_optimization' => $hq->getPendingRaceOptimization(),
            'is_optimization_locked' => ($hq->hasPendingRaceOptimizationChange() || $hq->isRaceOptimizationLockCycle()),
        ]);
    }

    private function getPlayerTeam(): ?\App\Entity\Team\Team
    {
        /** @var User|null $user */
        $user = $this->getUser();

        return $user?->getTeam();
    }
}
