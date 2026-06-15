<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Entity\Auth\User;
use App\Entity\Crafting\CraftingQueue;
use App\Entity\Crafting\CraftingRecipe;
use App\Service\Crafting\CraftingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class CraftingController extends AbstractController
{
    public function __construct(
        private readonly CraftingService $craftingService,
    ) {
    }

    #[Route('/api/v1/crafting/recipes', name: 'api_crafting_recipes', methods: ['GET'])]
    public function recipes(): JsonResponse
    {
        $recipes = $this->craftingService->listRecipes();

        return $this->json(array_map([$this, 'serializeRecipe'], $recipes));
    }

    #[Route('/api/v1/crafting/queue', name: 'api_crafting_queue', methods: ['GET'])]
    public function queue(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();
        if (!$team) {
            return $this->json(['error' => 'No team assigned.'], Response::HTTP_BAD_REQUEST);
        }

        $jobs = $this->craftingService->listQueueForTeam($team);

        return $this->json(array_map([$this, 'serializeQueueJob'], $jobs));
    }

    #[Route('/api/v1/crafting', name: 'api_crafting_start', methods: ['POST'])]
    public function start(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();
        if (!$team) {
            return $this->json(['error' => 'No team assigned.'], Response::HTTP_BAD_REQUEST);
        }

        $content = json_decode($request->getContent(), true) ?? [];
        $recipeId = (int) ($content['recipe_id'] ?? 0);

        try {
            $job = $this->craftingService->startJob($team, $recipeId, new \DateTimeImmutable('now'));

            return $this->json($this->serializeQueueJob($job), Response::HTTP_CREATED);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/api/v1/crafting/queue/{id}', name: 'api_crafting_cancel', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function cancel(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();
        if (!$team) {
            return $this->json(['error' => 'No team assigned.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->craftingService->cancelJob($team, $id);

            return $this->json(['success' => true]);
        } catch (\DomainException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /** @return array<string, mixed> */
    private function serializeRecipe(CraftingRecipe $recipe): array
    {
        return [
            'id' => $recipe->getId(),
            'result_category' => $recipe->getResultItemCategory()->value,
            'result_rarity' => $recipe->getResultItemRarity()->value,
            'required_materials' => $recipe->getRequiredMaterials(),
            'essence_cost_type' => $recipe->getEssenceCostType()?->value,
            'essence_cost_amount' => $recipe->getEssenceCostAmount(),
            'gold_cost' => $recipe->getGoldCost(),
            'success_rate_base' => $recipe->getSuccessRateBase(),
            'crafting_time' => $recipe->getCraftingTime(),
            'required_forge_level' => $recipe->getRequiredForgeLevel(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeQueueJob(CraftingQueue $job): array
    {
        return [
            'id' => $job->getId(),
            'status' => $job->getStatus()->value,
            'started_at' => $job->getStartedAt()->format(\DateTimeInterface::ATOM),
            'completes_at' => $job->getCompletesAt()->format(\DateTimeInterface::ATOM),
            'recipe' => $this->serializeRecipe($job->getRecipe()),
        ];
    }
}
