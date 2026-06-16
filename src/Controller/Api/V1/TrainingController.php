<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Entity\Auth\User;
use App\Entity\Training\Trainer;
use App\Enum\TrainingType;
use App\Repository\Hero\HeroRepository;
use App\Repository\Training\TrainerRepository;
use App\Service\Training\TrainerDismissalService;
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
        private readonly TrainerDismissalService $trainerDismissalService,
        private readonly HeroRepository $heroRepository,
        private readonly TrainerRepository $trainerRepository,
    ) {
    }

    /** List team's trainers, their configured training focus, slots limit, assigned heroes, and lock status. */
    #[Route('/training/trainers', name: 'api_training_trainers_list', methods: ['GET'])]
    public function trainers(): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->json(['error' => 'No team assigned to your account.'], 422);
        }

        $now = new \DateTimeImmutable();
        $isLocked = $this->trainingService->isTrainingLockedForTeam($team, $now);
        /** @var list<Trainer> $trainers */
        $trainers = $this->trainerRepository->findBy(['team' => $team]);

        $data = [];
        foreach ($trainers as $trainer) {
            $heroes = [];
            foreach ($trainer->getHeroes() as $hero) {
                $heroes[] = [
                    'id' => $hero->getId(),
                    'name' => $hero->getName(),
                    'level' => $hero->getLevel(),
                    'race' => $hero->getRace()->value,
                    'fatigue' => $hero->getFatigue(),
                    'form' => $hero->getForm(),
                ];
            }

            $data[] = [
                'id' => $trainer->getId(),
                'name' => $trainer->getName(),
                'race' => $trainer->getRace()->value,
                'training_type' => $trainer->getTrainingType()?->value,
                'target_attribute' => $trainer->getTargetAttribute(),
                'slots_limit' => $this->trainingService->getTrainerSlotsLimit($trainer),
                'slots_occupied' => count($heroes),
                'assigned_heroes' => $heroes,
                'is_locked' => $isLocked,
            ];
        }

        return $this->json([
            'trainers' => $data,
            'is_locked' => $isLocked,
            'next_tick' => $this->trainingService->getNextTrainingTime($now)->format(\DateTimeInterface::ATOM),
        ]);
    }

    /** Configure trainer focus. */
    #[Route('/training/trainers/{id}/configure', name: 'api_training_trainer_configure', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function configure(int $id, Request $request): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->json(['error' => 'No team assigned to your account.'], 422);
        }

        /** @var Trainer|null $trainer */
        $trainer = $this->trainerRepository->findOneBy(['id' => $id, 'team' => $team]);
        if (null === $trainer) {
            return $this->json(['error' => 'Trainer not found.'], 404);
        }

        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent(), true) ?? [];

        $typeValue = isset($body['type']) ? (string) $body['type'] : null;
        $type = null;
        if (null !== $typeValue && '' !== $typeValue) {
            $type = TrainingType::tryFrom($typeValue);
            if (null === $type) {
                return $this->json(['error' => 'Invalid training type.'], 400);
            }
        }

        $attribute = isset($body['attribute']) && '' !== trim((string) $body['attribute']) ? (string) $body['attribute'] : null;

        try {
            $this->trainingService->configureTrainer($trainer, $type, $attribute, $team, new \DateTimeImmutable());
        } catch (\DomainException|\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }

        return $this->json([
            'message' => 'Trainer configured successfully.',
            'training_type' => $trainer->getTrainingType()?->value,
            'target_attribute' => $trainer->getTargetAttribute(),
        ]);
    }

    /** Assign hero to trainer. */
    #[Route('/training/trainers/{id}/assign', name: 'api_training_trainer_assign', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function assign(int $id, Request $request): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->json(['error' => 'No team assigned to your account.'], 422);
        }

        /** @var Trainer|null $trainer */
        $trainer = $this->trainerRepository->findOneBy(['id' => $id, 'team' => $team]);
        if (null === $trainer) {
            return $this->json(['error' => 'Trainer not found.'], 404);
        }

        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent(), true) ?? [];
        $heroId = (int) ($body['hero_id'] ?? 0);

        $hero = $this->heroRepository->findOneBy(['id' => $heroId, 'team' => $team]);
        if (null === $hero) {
            return $this->json(['error' => 'Hero not found.'], 404);
        }

        try {
            $this->trainingService->assignHero($trainer, $hero, $team, new \DateTimeImmutable());
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }

        return $this->json([
            'message' => 'Hero assigned to trainer successfully.',
            'hero_id' => $hero->getId(),
            'trainer_id' => $trainer->getId(),
        ]);
    }

    /** Remove hero from trainer. */
    #[Route('/training/trainers/{id}/unassign', name: 'api_training_trainer_unassign', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function unassign(int $id, Request $request): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->json(['error' => 'No team assigned to your account.'], 422);
        }

        /** @var Trainer|null $trainer */
        $trainer = $this->trainerRepository->findOneBy(['id' => $id, 'team' => $team]);
        if (null === $trainer) {
            return $this->json(['error' => 'Trainer not found.'], 404);
        }

        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent(), true) ?? [];
        $heroId = (int) ($body['hero_id'] ?? 0);

        $hero = $this->heroRepository->findOneBy(['id' => $heroId, 'team' => $team]);
        if (null === $hero) {
            return $this->json(['error' => 'Hero not found.'], 404);
        }

        try {
            $this->trainingService->unassignHero($trainer, $hero, $team, new \DateTimeImmutable());
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }

        return $this->json([
            'message' => 'Hero unassigned from trainer successfully.',
            'hero_id' => $hero->getId(),
        ]);
    }

    #[Route('/training/trainers/{id}/dismiss', name: 'api_training_trainer_dismiss', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function dismiss(int $id): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->json(['error' => 'No team assigned to your account.'], 422);
        }

        /** @var Trainer|null $trainer */
        $trainer = $this->trainerRepository->findOneBy(['id' => $id, 'team' => $team]);
        if (null === $trainer) {
            return $this->json(['error' => 'Trainer not found.'], 404);
        }

        try {
            $compensation = $this->trainerDismissalService->dismiss($team, $trainer);
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
