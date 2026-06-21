<?php

declare(strict_types=1);

namespace App\Controller\Web;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class SummoningController extends AbstractController
{
    #[Route('/app/summon', name: 'app_summon', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('app_hq', ['facility' => 'summoning_chamber']);
    }

    #[Route('/app/summon/history', name: 'app_summon_history', methods: ['GET'])]
    public function history(): Response
    {
        return $this->redirectToRoute('app_hq', [
            'facility' => 'summoning_chamber',
            'subtab' => 'history',
        ]);
    }
}
