<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use App\Repository\Hero\HeroRepository;
use App\Repository\Spell\SpellRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class SpellController extends AbstractController
{
    public function __construct(
        private readonly HeroRepository $heroRepository,
        private readonly SpellRepository $spellRepository,
    ) {
    }

    #[Route('/app/spells', name: 'app_spells', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();

        if (!$team) {
            $this->addFlash('error', 'No team assigned to your account.');

            return $this->redirectToRoute('app_home');
        }

        $heroes = $this->heroRepository->findBy(['team' => $team]);
        $spells = $this->spellRepository->findAll();

        return $this->render('spell/index.html.twig', [
            'team' => $team,
            'heroes' => $heroes,
            'spells' => $spells,
        ]);
    }
}
