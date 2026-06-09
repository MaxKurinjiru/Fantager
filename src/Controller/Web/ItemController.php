<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use App\Repository\Hero\HeroRepository;
use App\Repository\Item\ItemRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class ItemController extends AbstractController
{
    public function __construct(
        private readonly HeroRepository $heroRepository,
        private readonly ItemRepository $itemRepository,
    ) {
    }

    #[Route('/app/inventory', name: 'app_inventory', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();

        if (!$team) {
            $this->addFlash('error', 'No team assigned to your account.');

            return $this->redirectToRoute('app_home');
        }

        $items = $this->itemRepository->findBy(['ownerTeam' => $team]);
        $heroes = $this->heroRepository->findBy(['team' => $team]);

        return $this->render('item/index.html.twig', [
            'team' => $team,
            'items' => $items,
            'heroes' => $heroes,
        ]);
    }
}
