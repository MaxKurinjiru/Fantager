<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use App\Service\Team\TeamService;
use App\Service\TeamChronicle\TeamChroniclePresenter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly TeamService $teamService,
        private readonly TeamChroniclePresenter $teamChroniclePresenter,
    ) {
    }

    #[Route('/app/dashboard', name: 'app_dashboard')]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();

        if (!$team) {
            $this->addFlash('error', 'No team assigned to your account.');

            return $this->redirectToRoute('app_home');
        }

        $dashboardData = $this->teamService->getDashboardData($team);
        $recentChronicle = $this->teamChroniclePresenter->presentRecentForTeam($team, 5, $user->getLocale());

        return $this->render('dashboard/index.html.twig', [
            'user' => $user,
            'team' => $team,
            'dashboard' => $dashboardData,
            'recent_chronicle' => $recentChronicle,
        ]);
    }
}
