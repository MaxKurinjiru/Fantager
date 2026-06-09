<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class SummoningController extends AbstractController
{
    public function __construct(
        private readonly \App\Service\Summoning\SummoningService $summoningService,
    ) {
    }

    #[Route('/app/summon', name: 'app_summon', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();

        if (!$team) {
            $this->addFlash('error', 'No team assigned to your account.');

            return $this->redirectToRoute('app_home');
        }

        $status = $this->summoningService->getStatus($team);
        $arenaTheme = $this->summoningService->getArenaRaceTheme($team);
        $compatibleRaces = $this->summoningService->getCompatibleRacesDetails($team);

        return $this->render('summoning/index.html.twig', [
            'team' => $team,
            'status' => $status,
            'arena_theme' => $arenaTheme,
            'compatible_races' => $compatibleRaces,
        ]);
    }
}
