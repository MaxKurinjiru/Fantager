<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use App\Repository\Formation\FormationRepository;
use App\Repository\Hero\HeroRepository;
use App\Service\Formation\FixtureFormationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class FormationController extends AbstractController
{
    public function __construct(
        private readonly HeroRepository $heroRepository,
        private readonly FormationRepository $formationRepository,
        private readonly FixtureFormationService $fixtureFormationService,
    ) {
    }

    #[Route('/app/formation', name: 'app_formation', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();

        if (!$team) {
            $this->addFlash('error', 'No team assigned to your account.');

            return $this->redirectToRoute('app_home');
        }

        $heroes = $this->heroRepository->findBy(['team' => $team]);
        $formations = $this->formationRepository->findSavedByTeam($team);

        $fixture = null;
        $fixtureAssignment = null;
        $fixtureId = $request->query->get('fixture_id');
        if (null !== $fixtureId && '' !== $fixtureId && is_numeric($fixtureId)) {
            $fixture = $this->fixtureFormationService->findFixtureForTeam((int) $fixtureId, $team);
            if (null !== $fixture) {
                try {
                    $fixtureAssignment = $this->fixtureFormationService->getAssignmentState($fixture, $team);
                } catch (\DomainException) {
                    $fixture = null;
                }
            }
        }

        return $this->render('formation/index.html.twig', [
            'team' => $team,
            'heroes' => $heroes,
            'formations' => $formations,
            'fixture' => $fixture,
            'fixture_assignment' => $fixtureAssignment,
        ]);
    }
}
