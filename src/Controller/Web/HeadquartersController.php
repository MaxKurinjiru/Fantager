<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use App\Repository\Headquarters\HeadquartersRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class HeadquartersController extends AbstractController
{
    public function __construct(
        private readonly HeadquartersRepository $hqRepository,
    ) {
    }

    #[Route('/app/hq', name: 'app_hq', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();

        if (!$team) {
            $this->addFlash('error', 'No team assigned to your account.');

            return $this->redirectToRoute('app_home');
        }

        $hq = $this->hqRepository->findOneBy(['team' => $team]);

        return $this->render('hq/index.html.twig', [
            'team' => $team,
            'hq' => $hq,
        ]);
    }
}
