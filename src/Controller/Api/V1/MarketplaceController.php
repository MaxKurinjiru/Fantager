<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Controller\Api\ApiControllerTrait;
use App\Entity\Auth\User;
use App\Service\Marketplace\MarketplaceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class MarketplaceController extends AbstractController
{
    use ApiControllerTrait;

    public function __construct(
        private readonly MarketplaceService $marketplaceService,
    ) {
    }

    #[Route('/api/v1/marketplace', name: 'api_marketplace_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();
        if (!$team) {
            return $this->jsonError('error.no_team', 400);
        }

        $listings = $this->marketplaceService->searchListings($team->getKingdom(), [
            'type' => $request->query->get('type'),
            'race' => $request->query->get('race'),
            'level_min' => $request->query->get('level_min'),
            'level_max' => $request->query->get('level_max'),
            'rarity' => $request->query->get('rarity'),
            'price_min' => $request->query->get('price_min'),
            'price_max' => $request->query->get('price_max'),
            'search' => $request->query->get('search'),
            'sort' => $request->query->get('sort', 'newest'),
        ]);

        return new JsonResponse(array_map(
            $this->marketplaceService->serializeListing(...),
            $listings,
        ));
    }

    #[Route('/api/v1/marketplace/listings', name: 'api_marketplace_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();
        if (!$team) {
            return $this->jsonError('error.no_team', 400);
        }

        $content = json_decode($request->getContent(), true) ?? [];
        $type = (string) ($content['type'] ?? '');
        $entityId = (int) ($content['entity_id'] ?? 0);
        $priceGold = (int) ($content['price_gold'] ?? 0);
        $buyoutPriceGold = isset($content['buyout_price_gold']) && '' !== $content['buyout_price_gold'] ? (int) $content['buyout_price_gold'] : null;
        $mode = (string) ($content['mode'] ?? '');
        $durationDays = (int) ($content['duration_days'] ?? 7);

        try {
            $listing = $this->marketplaceService->createListing(
                $team,
                $type,
                $entityId,
                $priceGold,
                $buyoutPriceGold,
                $mode,
                $durationDays,
                new \DateTimeImmutable('now')
            );

            return new JsonResponse(
                $this->marketplaceService->serializeListing($listing),
                Response::HTTP_CREATED,
            );
        } catch (\DomainException|\InvalidArgumentException $e) {
            return $this->jsonException($e, 400);
        }
    }

    #[Route('/api/v1/marketplace/listings/{id}', name: 'api_marketplace_cancel', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function cancel(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();
        if (!$team) {
            return $this->jsonError('error.no_team', 400);
        }

        try {
            $this->marketplaceService->cancelListing($team, $id);

            return new JsonResponse(['success' => true]);
        } catch (\DomainException $e) {
            return $this->jsonException($e, 400);
        }
    }

    #[Route('/api/v1/marketplace/purchase', name: 'api_marketplace_purchase', methods: ['POST'])]
    public function purchase(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();
        if (!$team) {
            return $this->jsonError('error.no_team', 400);
        }

        $content = json_decode($request->getContent(), true) ?? [];
        $listingId = (int) ($content['listing_id'] ?? 0);

        try {
            $this->marketplaceService->buyListing($team, $listingId, new \DateTimeImmutable('now'));

            return new JsonResponse(['success' => true]);
        } catch (\DomainException $e) {
            return $this->jsonException($e, 400);
        }
    }

    #[Route('/api/v1/marketplace/bid', name: 'api_marketplace_bid', methods: ['POST'])]
    public function bid(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();
        if (!$team) {
            return $this->jsonError('error.no_team', 400);
        }

        $content = json_decode($request->getContent(), true) ?? [];
        $listingId = (int) ($content['listing_id'] ?? 0);
        $bidAmount = (int) ($content['bid_amount'] ?? 0);

        try {
            $this->marketplaceService->placeBid($team, $listingId, $bidAmount, new \DateTimeImmutable('now'));

            return new JsonResponse(['success' => true]);
        } catch (\DomainException $e) {
            return $this->jsonException($e, 400);
        }
    }

    #[Route('/api/v1/marketplace/my-listings', name: 'api_marketplace_my_listings', methods: ['GET'])]
    public function myListings(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();
        if (!$team) {
            return $this->jsonError('error.no_team', 400);
        }

        return new JsonResponse(array_map(
            $this->marketplaceService->serializeListing(...),
            $this->marketplaceService->getListingsForSeller($team),
        ));
    }

    #[Route('/api/v1/marketplace/history', name: 'api_marketplace_history', methods: ['GET'])]
    public function history(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();
        if (!$team) {
            return $this->jsonError('error.no_team', 400);
        }

        return new JsonResponse($this->marketplaceService->getTransactionHistory($team));
    }
}
