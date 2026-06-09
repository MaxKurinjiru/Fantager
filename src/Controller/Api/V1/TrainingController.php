<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Entity\Auth\User;
use App\Enum\TrainingType;
use App\Repository\Hero\HeroRepository;
use App\Repository\Training\TrainingQueueRepository;
use App\Service\Training\TrainingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1')]
class TrainingController extends AbstractController
{
    public function __construct(
        private readonly TrainingService $trainingService,
        private readonly HeroRepository $heroRepository,
        private readonly TrainingQueueRepository $queueRepository,
    ) {
    }

    /** Training options (available types and costs for a given hero). */
    #[Route('/training', name: 'api_training_options', methods: ['GET'])]
    public function options(Request $request): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->json(['error' => 'No team assigned to your account.'], 422);
        }

        $heroId = (int) $request->query->get('hero_id', 0);
        if (0 === $heroId) {
            return $this->json(['error' => 'Query parameter "hero_id" is required.'], 400);
        }

        $hero = $this->heroRepository->findOneBy(['id' => $heroId, 'team' => $team]);
        if (null === $hero) {
            return $this->json(['error' => 'Hero not found.'], 404);
        }

        return $this->json($this->trainingService->getOptions($hero));
    }

    /** List pending/in-progress training jobs for the current team. */
    #[Route('/training-queue', name: 'api_training_queue_list', methods: ['GET'])]
    public function queue(): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->json(['error' => 'No team assigned to your account.'], 422);
        }

        $jobs = $this->trainingService->getQueueByTeam($team);

        return $this->json(array_map($this->trainingService->serialize(...), $jobs));
    }

    /** Add a training job to the queue. */
    #[Route('/training-queue', name: 'api_training_queue_add', methods: ['POST'])]
    public function add(Request $request): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->json(['error' => 'No team assigned to your account.'], 422);
        }

        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent(), true) ?? [];

        $heroId = (int) ($body['hero_id'] ?? 0);
        if (0 === $heroId) {
            return $this->json(['error' => 'Field "hero_id" is required.'], 400);
        }

        $hero = $this->heroRepository->findOneBy(['id' => $heroId, 'team' => $team]);
        if (null === $hero) {
            return $this->json(['error' => 'Hero not found.'], 404);
        }

        $typeValue = (string) ($body['type'] ?? '');
        $type = TrainingType::tryFrom($typeValue);
        if (null === $type) {
            $valid = implode(', ', array_column(TrainingType::cases(), 'value'));

            return $this->json(['error' => sprintf('Invalid training type. Valid values: %s.', $valid)], 400);
        }

        $attribute = isset($body['attribute']) ? (string) $body['attribute'] : null;
        $trainerId = isset($body['trainer_id']) ? (int) $body['trainer_id'] : null;

        try {
            $job = $this->trainingService->queue($hero, $type, $attribute, $trainerId, $team);
        } catch (\DomainException|\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }

        return $this->json($this->trainingService->serialize($job), 201);
    }

    /** Cancel a pending training job (50% gold refund). */
    #[Route('/training-queue/{id}', name: 'api_training_queue_cancel', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function cancel(int $id): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->json(['error' => 'No team assigned to your account.'], 422);
        }

        $job = $this->queueRepository->find($id);
        if (null === $job) {
            return $this->json(['error' => 'Training job not found.'], 404);
        }

        try {
            $this->trainingService->cancel($job, $team);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }

        return $this->json(['message' => 'Training job cancelled. Partial refund applied.']);
    }

    private function getPlayerTeam(): ?\App\Entity\Team\Team
    {
        /** @var User|null $user */
        $user = $this->getUser();

        return $user?->getTeam();
    }
}
