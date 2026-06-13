<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use App\Entity\Hero\Hero;
use App\Entity\Item\Item;
use App\Entity\Training\Trainer;
use App\Enum\HeroStatus;
use App\Enum\TrainerStatus;
use App\Enum\ItemStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class MarketplaceController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/app/marketplace', name: 'app_marketplace')]
    public function hub(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();

        if (!$team) {
            $this->addFlash('error', 'No team assigned to your account.');

            return $this->redirectToRoute('app_home');
        }

        // Fetch sellable entities (escrow candidates)
        $heroes = $this->em->getRepository(Hero::class)->findBy([
            'team' => $team,
            'status' => [HeroStatus::Available, HeroStatus::Tired]
        ]);
        $items = $this->em->getRepository(Item::class)->findBy([
            'ownerTeam' => $team,
            'status' => ItemStatus::Available,
            'equippedHero' => null
        ]);
        $trainers = $this->em->getRepository(Trainer::class)->findBy([
            'team' => $team,
            'status' => TrainerStatus::Active
        ]);

        return $this->render('marketplace/hub.html.twig', [
            'team' => $team,
            'heroes' => $heroes,
            'items' => $items,
            'trainers' => $trainers,
            'taxRate' => (float) $team->getKingdom()->getMarketplaceTaxRate(),
        ]);
    }
}
