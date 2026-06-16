<?php

declare(strict_types=1);

namespace App\Controller\Web;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class TrainingController extends AbstractController
{
    #[Route('/app/training', name: 'app_training', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $heroId = $request->query->get('hero_id');
        if ($heroId) {
            return $this->redirectToRoute('app_heroes_detail', [
                'id' => $heroId,
                'tab' => 'training',
            ]);
        }

        return $this->redirectToRoute('app_hq', ['facility' => 'training']);
    }
}
