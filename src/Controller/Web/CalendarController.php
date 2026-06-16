<?php

declare(strict_types=1);

namespace App\Controller\Web;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class CalendarController extends AbstractController
{
    #[Route('/app/calendar', name: 'app_calendar', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('app_league', ['tab' => 'calendar']);
    }
}
