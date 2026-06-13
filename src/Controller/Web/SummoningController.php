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
        private readonly \App\Repository\Summoning\SummonHistoryRepository $historyRepository,
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

    #[Route('/app/summon/history', name: 'app_summon_history', methods: ['GET'])]
    public function history(\Symfony\Component\HttpFoundation\Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();

        if (!$team) {
            $this->addFlash('error', 'No team assigned to your account.');

            return $this->redirectToRoute('app_home');
        }

        $race = $request->query->get('race');
        if ($race === '') {
            $race = null;
        }
        $sort = $request->query->get('sort', 'date-desc');

        $history = $this->historyRepository->findByTeamFiltered($team, $race, $sort);

        return $this->render('summoning/history.html.twig', [
            'team' => $team,
            'history' => $history,
            'current_race' => $race,
            'current_sort' => $sort,
        ]);
    }
}
