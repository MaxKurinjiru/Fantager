<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use App\Entity\Hero\Hero;
use App\Entity\Item\Item;
use App\Enum\HeroRole;
use App\Enum\HeroStatus;
use App\Enum\ItemStatus;
use App\Service\Translation\UserMessageTranslator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class MarketplaceController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
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
        if (!in_array($tab, ['browse', 'sell', 'mylistings', 'history'], true)) {
            $tab = 'browse';
        }

        $category = $request->query->get('category', 'hero');
        if (!in_array($category, ['hero', 'item', 'trainer'], true)) {
            $category = 'hero';
        }

        $heroes = $this->em->getRepository(Hero::class)->findBy([
            'team' => $team,
            'role' => HeroRole::Combatant,
            'status' => HeroStatus::Available,
        ]);
        $items = $this->em->getRepository(Item::class)->findBy([
            'ownerTeam' => $team,
            'status' => ItemStatus::Available,
            'equippedHero' => null,
        ]);
        $trainers = $this->em->getRepository(Hero::class)->findBy([
            'team' => $team,
            'role' => HeroRole::Trainer,
            'status' => HeroStatus::Available,
        ]);

        $sellHeroId = $request->query->getInt('hero');
        if ($sellHeroId <= 0) {
            $sellHeroId = null;
        }

        return $this->render('marketplace/index.html.twig', [
            'team' => $team,
            'tab' => $tab,
            'category' => $category,
            'heroes' => $heroes,
            'items' => $items,
            'trainers' => $trainers,
            'taxRate' => (float) $team->getKingdom()->getMarketplaceTaxRate(),
            'sell_hero_id' => $sellHeroId,
        ]);
    }
}
