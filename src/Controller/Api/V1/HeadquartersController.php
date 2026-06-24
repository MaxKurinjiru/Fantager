<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Controller\Api\ApiControllerTrait;
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
    use ApiControllerTrait;

    public function __construct(
        private readonly HeadquartersService $hqService,
    ) {
    }

    #[Route('', name: 'api_hq_get', methods: ['GET'])]
    public function get(): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->jsonError('error.no_team', 422);
        }

        try {
            $hq = $this->hqService->getForTeam($team);
        } catch (\DomainException $e) {
            return $this->jsonException($e, 404);
        }

        $facilities = [];
        foreach ($hq->getFacilities() as $facility) {
            $facilities[] = $this->hqService->serializeFacility($facility, $facility->getType(), $hq);
        }

        $maintenance = $this->hqService->calculateWeeklyMaintenanceBreakdown($hq);

        $upgradingType = $hq->getUpgradingFacility()?->getType()?->value;
        $completedAt = $hq->getUpgradeCompletedAt()?->format(\DateTimeInterface::ATOM);
        $facilityOperation = $hq->getFacilityOperation()?->value;

        return $this->json([
            'total_level' => $hq->getComputedTotalLevel(),
            'weekly_maintenance_fee' => $maintenance['total'],
            'race_optimization' => $hq->getRaceOptimization(),
            'pending_race_optimization' => $hq->getPendingRaceOptimization(),
            'is_optimization_locked' => ($hq->hasPendingRaceOptimizationChange() || $hq->isRaceOptimizationLockCycle()),
            'changing_facility' => $upgradingType,
            'facility_operation' => $facilityOperation,
            'change_completed_at' => $completedAt,
            'is_downgrade_locked' => $hq->isFacilityDowngradeLockCycle(),
            'upgrading_facility' => $upgradingType,
            'upgrade_completed_at' => $completedAt,
            'facilities' => $facilities,
        ]);
    }

    #[Route('/downgrade', name: 'api_hq_downgrade', methods: ['POST'])]
    public function downgrade(Request $request): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->jsonError('error.no_team', 422);
        }

        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent(), true) ?? [];

        $facilityValue = (string) ($body['facility'] ?? '');
        $type = FacilityType::tryFrom($facilityValue);
        if (null === $type) {
            $valid = implode(', ', array_column(FacilityType::cases(), 'value'));

            return $this->jsonError('error.invalid_facility_type', 400, ['%values%' => $valid]);
        }

        try {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $facility = $this->hqService->downgradeFacility($team, $type, $now);
        } catch (\DomainException $e) {
            return $this->jsonException($e, 422);
        }

        return $this->json($this->hqService->serializeFacility($facility, $type, $this->hqService->getForTeam($team)));
    }

    #[Route('/upgrade', name: 'api_hq_upgrade', methods: ['POST'])]
    public function upgrade(Request $request): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->jsonError('error.no_team', 422);
        }

        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent(), true) ?? [];

        $facilityValue = (string) ($body['facility'] ?? '');
        $type = FacilityType::tryFrom($facilityValue);
        if (null === $type) {
            $valid = implode(', ', array_column(FacilityType::cases(), 'value'));

            return $this->jsonError('error.invalid_facility_type', 400, ['%values%' => $valid]);
        }

        try {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $facility = $this->hqService->upgradeFacility($team, $type, $now);
        } catch (\DomainException $e) {
            return $this->jsonException($e, 422);
        }

        return $this->json($this->hqService->serializeFacility($facility, $type, $this->hqService->getForTeam($team)));
    }

    #[Route('/cancel-upgrade', name: 'api_hq_cancel_upgrade', methods: ['POST'])]
    public function cancelUpgrade(): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->jsonError('error.no_team', 422);
        }

        try {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $this->hqService->cancelUpgrade($team, $now);
        } catch (\DomainException $e) {
            return $this->jsonException($e, 422);
        }

        return $this->json(['success' => true]);
    }

    /** Schedule an arena adaptation change (route kept as `/optimize` for API stability). */
    #[Route('/optimize', name: 'api_hq_optimize', methods: ['POST'])]
    public function optimize(Request $request): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->jsonError('error.no_team', 422);
        }

        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent(), true) ?? [];
        $raceValue = isset($body['race']) && '' !== $body['race'] ? (string) $body['race'] : null;

        try {
            $hq = $this->hqService->updateRaceOptimization($team, $raceValue);
        } catch (\DomainException $e) {
            return $this->jsonException($e, 422);
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
