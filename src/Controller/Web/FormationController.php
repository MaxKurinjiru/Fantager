<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use App\Enum\HeroStatus;
use App\Repository\Formation\FormationRepository;
use App\Repository\Hero\HeroRepository;
use App\Service\Formation\FixtureFormationService;
use App\Service\Translation\UserMessageTranslator;
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
        private readonly UserMessageTranslator $userMessages,
    ) {
    }

    #[Route('/app/formation', name: 'app_formation', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();

        if (!$team) {
            $this->addFlash('error', $this->userMessages->trans('error.no_team'));

            return $this->redirectToRoute('app_home');
        }

        $heroes = array_values(array_filter(
            $this->heroRepository->findCombatantsByTeam($team),
            static fn ($hero) => HeroStatus::Dead !== $hero->getStatus(),
        ));
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
