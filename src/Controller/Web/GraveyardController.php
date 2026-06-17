<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use App\Enum\HeroRole;
use App\Enum\MemorialCause;
use App\Enum\Race;
use App\Service\Graveyard\GraveyardPresenter;
use App\Service\Translation\UserMessageTranslator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class GraveyardController extends AbstractController
{
    public function __construct(
        private readonly GraveyardPresenter $graveyardPresenter,
        private readonly UserMessageTranslator $userMessages,
    ) {
    }

    #[Route('/app/graveyard', name: 'app_graveyard', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();

        if (!$team) {
            $this->addFlash('error', $this->userMessages->trans('error.no_team'));

            return $this->redirectToRoute('app_home');
        }

        $roleValue = $request->query->get('role');
        $role = is_string($roleValue) && '' !== $roleValue
            ? HeroRole::tryFrom($roleValue)
            : null;

        $causeValue = $request->query->get('cause');
        $cause = is_string($causeValue) && '' !== $causeValue
            ? MemorialCause::tryFrom($causeValue)
            : null;

        $raceValue = $request->query->get('race');
        $race = is_string($raceValue) && '' !== $raceValue
            ? Race::tryFrom($raceValue)
            : null;

        $search = $request->query->get('search');
        $search = is_string($search) ? trim($search) : null;
        if ('' === $search) {
            $search = null;
        }

        $detailId = (int) $request->query->get('id', 0);
        $selectedMemorial = $detailId > 0
            ? $this->graveyardPresenter->findForTeam($detailId, $team)
            : null;

        return $this->render('graveyard/index.html.twig', [
            'team' => $team,
            'summary' => $this->graveyardPresenter->presentSummary($team),
            'memorials' => $this->graveyardPresenter->presentListForTeam($team, $role, $cause, $race, $search),
            'selected_memorial' => $selectedMemorial,
            'current_role' => $role?->value,
            'current_cause' => $cause?->value,
            'current_race' => $race?->value,
            'current_search' => $search,
            'roles' => HeroRole::cases(),
            'causes' => MemorialCause::cases(),
            'races' => Race::cases(),
        ]);
    }
}
