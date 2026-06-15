<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class CalendarController extends AbstractController
{
    #[Route('/app/calendar', name: 'app_calendar', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();

        if (!$team) {
            $this->addFlash('error', 'No team assigned to your account.');

            return $this->redirectToRoute('app_home');
        }

        $kingdom = $team->getKingdom();

        return $this->render('calendar/index.html.twig', [
            'team' => $team,
            'kingdom' => $kingdom,
        ]);
    }
}
