<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use App\Repository\Hero\HeroRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class HeroController extends AbstractController
{
    public function __construct(
        private readonly HeroRepository $heroRepository,
        private readonly \App\Repository\Item\ItemRepository $itemRepository,
        private readonly \App\Repository\Training\TrainingQueueRepository $trainingQueueRepository,
        private readonly \App\Service\Config\RaceConfig $raceConfig,
    ) {
    }

    #[Route('/app/heroes', name: 'app_heroes', methods: ['GET'])]
    public function roster(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();

        if (!$team) {
            $this->addFlash('error', 'No team assigned to your account.');

            return $this->redirectToRoute('app_home');
        }

        $heroes = $this->heroRepository->findBy(['team' => $team]);

        return $this->render('hero/roster.html.twig', [
            'team' => $team,
            'heroes' => $heroes,
        ]);
    }

    #[Route('/app/heroes/{id}', name: 'app_heroes_detail', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function detail(int $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();

        if (!$team) {
            $this->addFlash('error', 'No team assigned to your account.');

            return $this->redirectToRoute('app_home');
        }

        /** @var \App\Entity\Hero\Hero|null $hero */
        $hero = $this->heroRepository->find($id);

        if (!$hero || $hero->getTeam()->getId() !== $team->getId()) {
            throw $this->createNotFoundException('Hero not found.');
        }

        $equipped = $this->itemRepository->findBy(['equippedHero' => $hero]);
        $equippedBySlot = [];
        foreach ($equipped as $item) {
            if ($item->getEquippedSlot()) {
                $equippedBySlot[$item->getEquippedSlot()->value] = $item;
            }
        }

        // Fetch training history (last 10 completed/cancelled/processed jobs)
        $trainingHistory = $this->trainingQueueRepository->findBy(
            ['hero' => $hero],
            ['completedAt' => 'DESC', 'id' => 'DESC'],
            10
        );

        $statBonuses = $this->raceConfig->getStatBonuses($hero->getRace());

        return $this->render('hero/detail.html.twig', [
            'team' => $team,
            'hero' => $hero,
            'equipped' => $equippedBySlot,
            'trainingHistory' => $trainingHistory,
            'statBonuses' => $statBonuses,
        ]);
    }
}
