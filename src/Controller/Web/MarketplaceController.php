<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use App\Service\Item\ItemService;
use App\Service\Marketplace\MarketplaceService;
use App\Service\Translation\UserMessageTranslator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class MarketplaceController extends AbstractController
{
    public function __construct(
        private readonly MarketplaceService $marketplaceService,
        private readonly ItemService $itemService,
        private readonly UserMessageTranslator $userMessages,
    ) {
    }

    #[Route('/app/marketplace', name: 'app_marketplace', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();

        if (!$team) {
            $this->addFlash('error', $this->userMessages->trans('error.no_team'));

            return $this->redirectToRoute('app_home');
        }

        $tab = $request->query->get('tab', 'browse');
        if (!in_array($tab, ['browse', 'sell', 'mylistings', 'history', 'basic_equipment'], true)) {
            $tab = 'browse';
        }

        $category = $request->query->get('category', 'hero');
        if (!in_array($category, ['hero', 'item', 'trainer'], true)) {
            $category = 'hero';
        }

        $assets = $this->marketplaceService->getSellableAssets($team);

        $sellHeroId = $request->query->getInt('hero');
        if ($sellHeroId <= 0) {
            $sellHeroId = null;
        }

        return $this->render('marketplace/index.html.twig', [
            'team' => $team,
            'tab' => $tab,
            'category' => $category,
            'heroes' => $assets['heroes'],
            'items' => $assets['items'],
            'trainers' => $assets['trainers'],
            'taxRate' => (float) $team->getKingdom()->getMarketplaceTaxRate(),
            'sell_hero_id' => $sellHeroId,
            'basic_items' => ItemService::BASIC_EQUIPMENT,
        ]);
    }

    #[Route('/app/marketplace/buy-basic', name: 'app_marketplace_buy_basic', methods: ['POST'])]
    public function buyBasic(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();

        if (!$team) {
            $this->addFlash('error', $this->userMessages->trans('error.no_team'));

            return $this->redirectToRoute('app_home');
        }

        $csrfToken = $request->request->get('_csrf_token');
        if (!is_string($csrfToken) || !$this->isCsrfTokenValid('buy_basic', $csrfToken)) {
            $this->addFlash('error', $this->userMessages->trans('error.invalid_csrf'));

            return $this->redirectToRoute('app_marketplace', ['tab' => 'basic_equipment']);
        }

        $itemKey = $request->request->get('item_key');
        if (!is_string($itemKey)) {
            $itemKey = '';
        }

        try {
            $item = $this->itemService->purchaseBasicItem($team, $itemKey);
            $this->addFlash('success', $this->userMessages->trans('marketplace.flash_basic_item_purchased', [
                '%item%' => $item->getName(),
                '%cost%' => ItemService::BASIC_EQUIPMENT[$itemKey]['cost'] ?? 0,
            ]));
        } catch (\Throwable $e) {
            $this->addFlash('error', $this->userMessages->fromException($e));
        }

        return $this->redirectToRoute('app_marketplace', ['tab' => 'basic_equipment']);
    }
}
