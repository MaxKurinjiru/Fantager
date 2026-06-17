<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Controller\Api\ApiControllerTrait;
use App\Entity\Auth\User;
use App\Enum\ItemSlotType;
use App\Repository\Hero\HeroRepository;
use App\Repository\Item\ItemRepository;
use App\Service\Item\ItemService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ItemController extends AbstractController
{
    use ApiControllerTrait;

    public function __construct(
        private readonly ItemService $itemService,
        private readonly HeroRepository $heroRepository,
        private readonly ItemRepository $itemRepository,
    ) {
    }

    /** List team inventory; optionally filtered by hero. */
    #[Route('/api/v1/items', name: 'api_items_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->jsonError('error.no_team', 422);
        }

        $hero = null;
        $heroId = (int) $request->query->get('hero_id', 0);
        if ($heroId > 0) {
            $hero = $this->heroRepository->findOneBy(['id' => $heroId, 'team' => $team]);
            if (null === $hero) {
                return $this->jsonError('error.hero_not_found', 404);
            }
        }

        $items = $this->itemService->listByTeam($team, $hero);

        return $this->json(array_map($this->itemService->serialize(...), $items));
    }

    /**
     * Equip or unequip an item on a hero.
     *
     * Body: { "item_id": 5, "slot": "main_hand" }
     * To unequip: { "item_id": 5, "slot": null }
     */
    #[Route('/api/v1/heroes/{heroId}/equipment', name: 'api_heroes_equip', methods: ['PUT'], requirements: ['heroId' => '\d+'])]
    public function equipment(int $heroId, Request $request): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->jsonError('error.no_team', 422);
        }

        $hero = $this->heroRepository->findOneBy(['id' => $heroId, 'team' => $team]);
        if (null === $hero) {
            return $this->jsonError('error.hero_not_found', 404);
        }

        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent(), true) ?? [];

        $itemId = (int) ($body['item_id'] ?? 0);
        if (0 === $itemId) {
            return $this->jsonError('error.field_item_id_required', 400);
        }

        $item = $this->itemService->findForTeam($itemId, $team);
        if (null === $item) {
            return $this->jsonError('error.item_not_found', 404);
        }

        // slot: null means unequip
        if (!array_key_exists('slot', $body) || null === $body['slot']) {
            try {
                $this->itemService->unequip($item);
            } catch (\DomainException $e) {
                return $this->jsonException($e, 422);
            }

            return $this->json($this->itemService->serialize($item));
        }

        $slotValue = (string) $body['slot'];
        $slot = ItemSlotType::tryFrom($slotValue);
        if (null === $slot) {
            $valid = implode(', ', array_column(ItemSlotType::cases(), 'value'));

            return $this->jsonError('error.invalid_slot', 400, ['%values%' => $valid]);
        }

        try {
            $this->itemService->equip($item, $hero, $slot);
        } catch (\DomainException $e) {
            return $this->jsonException($e, 422);
        }

        return $this->json($this->itemService->serialize($item));
    }

    /** Dismantle an item and receive essence. Body: { "item_id": 5 } */
    #[Route('/api/v1/items/dismantle', name: 'api_items_dismantle', methods: ['POST'])]
    public function dismantle(Request $request): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->jsonError('error.no_team', 422);
        }

        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent(), true) ?? [];

        $itemId = (int) ($body['item_id'] ?? 0);
        if (0 === $itemId) {
            return $this->jsonError('error.field_item_id_required', 400);
        }

        $item = $this->itemService->findForTeam($itemId, $team);
        if (null === $item) {
            return $this->jsonError('error.item_not_found', 404);
        }

        try {
            $result = $this->itemService->dismantle($item, $team);
        } catch (\DomainException $e) {
            return $this->jsonException($e, 422);
        }

        return $this->json($result);
    }

    /** Repair an item to full durability. Body: { "item_id": 5 } */
    #[Route('/api/v1/items/{id}/repair', name: 'api_items_repair', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function repair(int $id): JsonResponse
    {
        $team = $this->getPlayerTeam();
        if (null === $team) {
            return $this->jsonError('error.no_team', 422);
        }

        $item = $this->itemService->findForTeam($id, $team);
        if (null === $item) {
            return $this->jsonError('error.item_not_found', 404);
        }

        try {
            $goldSpent = $this->itemService->repair($item, $team);
        } catch (\DomainException $e) {
            return $this->jsonException($e, 422);
        }

        return $this->json([
            'message' => 'Item repaired.',
            'gold_spent' => $goldSpent,
            'item' => $this->itemService->serialize($item),
        ]);
    }

    private function getPlayerTeam(): ?\App\Entity\Team\Team
    {
        /** @var User|null $user */
        $user = $this->getUser();

        return $user?->getTeam();
    }
}
