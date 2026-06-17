<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Controller\Api\ApiControllerTrait;
use App\Entity\Auth\User;
use App\Entity\Marketplace\MarketplaceListing;
use App\Entity\Marketplace\MarketplaceTransaction;
use App\Enum\ListingStatus;
use App\Enum\ListingType;
use App\Service\Marketplace\MarketplaceService;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly EntityManagerInterface $em,
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

        $kingdom = $team->getKingdom();

        $qb = $this->em->createQueryBuilder();
        $qb->select('l')
           ->from(MarketplaceListing::class, 'l')
           ->where('l.kingdom = :kingdom')
           ->andWhere('l.status = :active')
           ->setParameter('kingdom', $kingdom)
           ->setParameter('active', ListingStatus::Active);

        $type = $request->query->get('type');
        if ($type) {
            $qb->andWhere('l.listingType = :type')
               ->setParameter('type', $type);
        }

        $race = $request->query->get('race');
        $levelMin = $request->query->get('level_min');
        $levelMax = $request->query->get('level_max');
        $rarity = $request->query->get('rarity');
        $priceMin = $request->query->get('price_min');
        $priceMax = $request->query->get('price_max');
        $search = $request->query->get('search');

        // Joins for Hero / Trainer filters (both use hero FK)
        if ($race || $levelMin || $levelMax || $search) {
            $qb->leftJoin('l.hero', 'h');
        }

        // Joins for Item filters
        if ($rarity || $search) {
            $qb->leftJoin('l.item', 'it');
        }

        if ($race) {
            $qb->andWhere('h.race = :race')
               ->setParameter('race', $race);
        }

        if (null !== $levelMin && '' !== $levelMin) {
            $qb->andWhere('h.level >= :levelMin')
               ->setParameter('levelMin', (int) $levelMin);
        }

        if (null !== $levelMax && '' !== $levelMax) {
            $qb->andWhere('h.level <= :levelMax')
               ->setParameter('levelMax', (int) $levelMax);
        }

        if ($rarity) {
            $qb->andWhere('it.rarity = :rarity')
               ->setParameter('rarity', $rarity);
        }

        if (null !== $priceMin && '' !== $priceMin) {
            $qb->andWhere('l.priceGold >= :priceMin OR l.buyoutPriceGold >= :priceMin')
               ->setParameter('priceMin', (int) $priceMin);
        }

        if (null !== $priceMax && '' !== $priceMax) {
            $qb->andWhere('l.priceGold <= :priceMax OR l.buyoutPriceGold <= :priceMax')
               ->setParameter('priceMax', (int) $priceMax);
        }

        if ($search) {
            $qb->andWhere('h.name LIKE :search OR it.name LIKE :search')
               ->setParameter('search', '%'.$search.'%');
        }

        $sort = $request->query->get('sort', 'newest');
        switch ($sort) {
            case 'price_asc':
                $qb->orderBy('l.priceGold', 'ASC');
                break;
            case 'price_desc':
                $qb->orderBy('l.priceGold', 'DESC');
                break;
            case 'expires_asc':
                $qb->orderBy('l.expiresAt', 'ASC');
                break;
            case 'newest':
            default:
                $qb->orderBy('l.id', 'DESC');
                break;
        }

        /** @var list<MarketplaceListing> $listings */
        $listings = $qb->getQuery()->getResult();

        $data = [];
        foreach ($listings as $listing) {
            $data[] = $this->serializeListing($listing);
        }

        return new JsonResponse($data);
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

            return new JsonResponse($this->serializeListing($listing), Response::HTTP_CREATED);
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

        /** @var list<MarketplaceListing> $listings */
        $listings = $this->em->getRepository(MarketplaceListing::class)->findBy(
            ['sellerTeam' => $team],
            ['id' => 'DESC']
        );

        $data = [];
        foreach ($listings as $listing) {
            $data[] = $this->serializeListing($listing);
        }

        return new JsonResponse($data);
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

        $transactions = $this->em->getRepository(MarketplaceTransaction::class)->createQueryBuilder('t')
            ->where('t.buyerTeam = :team OR t.sellerTeam = :team')
            ->setParameter('team', $team)
            ->orderBy('t.id', 'DESC')
            ->getQuery()
            ->getResult();

        $data = [];
        /** @var MarketplaceTransaction $tx */
        foreach ($transactions as $tx) {
            $highestBid = null;
            $listing = $tx->getListing();

            $data[] = [
                'id' => $tx->getId(),
                'buyer_name' => $tx->getBuyerTeam()->getName(),
                'seller_name' => $tx->getSellerTeam()->getName(),
                'amount' => $tx->getAmount(),
                'fee_amount' => $tx->getFeeAmount(),
                'type' => $tx->getType()->value,
                'created_at' => $tx->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'listing_id' => $listing->getId(),
                'listing_type' => $listing->getListingType()->value,
                'entity_name' => null !== $listing->getHero() ? $listing->getHero()->getName() :
                                 (ListingType::Item === $listing->getListingType() && null !== $listing->getItem() ? $listing->getItem()->getName() : 'Unknown'),
            ];
        }

        return new JsonResponse($data);
    }

    /** @return array<string, mixed> */
    private function serializeListing(MarketplaceListing $listing): array
    {
        $highestBid = null;
        foreach ($listing->getBids() as $bid) {
            if (null === $highestBid || $bid->getBidAmount() > $highestBid->getBidAmount()) {
                $highestBid = $bid;
            }
        }

        $entityData = null;
        if (ListingType::Hero === $listing->getListingType() && null !== $listing->getHero()) {
            $hero = $listing->getHero();
            $entityData = [
                'id' => $hero->getId(),
                'name' => $hero->getName(),
                'level' => $hero->getLevel(),
                'age' => $hero->getAge(),
                'race' => $hero->getRace()->value,
                'str' => $hero->getStr(),
                'dex' => $hero->getDex(),
                'kon' => $hero->getKon(),
                'spd' => $hero->getSpd(),
                'intel' => $hero->getIntel(),
                'wil' => $hero->getWil(),
                'cha' => $hero->getCha(),
                'lck' => $hero->getLck(),
                'form' => $hero->getForm(),
                'fatigue' => $hero->getFatigue(),
                'morale' => $hero->getMorale(),
            ];
        } elseif (ListingType::Item === $listing->getListingType() && null !== $listing->getItem()) {
            $item = $listing->getItem();
            $entityData = [
                'id' => $item->getId(),
                'name' => $item->getName(),
                'rarity' => $item->getRarity()->value,
                'slot_type' => $item->getSlotType()->value,
                'category' => $item->getCategory()->value,
                'durability' => $item->getDurability(),
                'bonuses' => $item->getBonuses(),
            ];
        } elseif (ListingType::Trainer === $listing->getListingType() && null !== $listing->getHero()) {
            $trainer = $listing->getHero();
            $entityData = [
                'id' => $trainer->getId(),
                'name' => $trainer->getName(),
                'race' => $trainer->getRace()->value,
                'age' => $trainer->getAge(),
                'str' => $trainer->getStr(),
                'dex' => $trainer->getDex(),
                'kon' => $trainer->getKon(),
                'spd' => $trainer->getSpd(),
                'intel' => $trainer->getIntel(),
                'wil' => $trainer->getWil(),
                'cha' => $trainer->getCha(),
                'lck' => $trainer->getLck(),
            ];
        }

        return [
            'id' => $listing->getId(),
            'listing_type' => $listing->getListingType()->value,
            'listing_mode' => $listing->getListingMode()->value,
            'price_gold' => $listing->getPriceGold(),
            'buyout_price_gold' => $listing->getBuyoutPriceGold(),
            'expires_at' => $listing->getExpiresAt()->format(\DateTimeInterface::ATOM),
            'status' => $listing->getStatus()->value,
            'seller_team' => [
                'id' => $listing->getSellerTeam()->getId(),
                'name' => $listing->getSellerTeam()->getName(),
            ],
            'highest_bid' => $highestBid ? [
                'amount' => $highestBid->getBidAmount(),
                'bidder_name' => $highestBid->getBidderTeam()->getName(),
            ] : null,
            'entity' => $entityData,
        ];
    }
}
