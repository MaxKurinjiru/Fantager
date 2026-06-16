<?php

declare(strict_types=1);

namespace App\Controller\Web;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class ArenaController extends AbstractController
{
    #[Route('/app/arena', name: 'app_arena', methods: ['GET'])]
    public function index(): Response
    {
        return $this->redirectToRoute('app_hq', ['facility' => 'arena']);
    }
}
