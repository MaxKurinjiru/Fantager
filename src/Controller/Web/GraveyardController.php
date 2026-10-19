<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use App\Entity\Graveyard\GraveyardMemorial;
use App\Entity\Team\Team;
use App\Enum\HeroRole;
use App\Enum\MemorialCause;
use App\Enum\Race;
use App\Repository\Hero\HeroRepository;
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
        private readonly HeroRepository $heroRepository,
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

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 20;

        $totalItems = $this->graveyardPresenter->countFilteredForTeam($team, $role, $cause, $race, $search);
        $totalPages = max(1, (int) ceil($totalItems / $limit));

        if ($page > $totalPages && $totalItems > 0) {
            return $this->redirectToRoute('app_graveyard', array_merge($request->query->all(), ['page' => $totalPages]));
        }

        $detailId = (int) $request->query->get('id', 0);
        $selectedMemorial = $detailId > 0
            ? $this->graveyardPresenter->findForTeam($detailId, $team)
            : null;

        $memorials = $this->graveyardPresenter->presentListForTeam($team, $role, $cause, $race, $search, $page, $limit);

        return $this->render('graveyard/index.html.twig', [
            'team' => $team,
            'summary' => $this->graveyardPresenter->presentSummary($team),
            'memorials' => $memorials,
            'selected_memorial' => $selectedMemorial,
            'profile_hero_ids' => $this->profileHeroIds($team, $memorials, $selectedMemorial),
            'current_role' => $role?->value,
            'current_cause' => $cause?->value,
            'current_race' => $race?->value,
            'current_search' => $search,
            'roles' => HeroRole::cases(),
            'causes' => MemorialCause::liveCases(),
            'races' => Race::cases(),
            'page' => $page,
            'total_pages' => $totalPages,
        ]);
    }

    /**
     * Hero ids that still belong to this team, so memorial links skip deleted rows.
     *
     * @param list<array<string, mixed>> $memorials
     *
     * @return array<int, true>
     */
    private function profileHeroIds(Team $team, array $memorials, ?GraveyardMemorial $selectedMemorial): array
    {
        $ids = [];
        foreach ($memorials as $memorial) {
            $heroId = $memorial['original_hero_id'] ?? null;
            if (is_int($heroId) && $heroId > 0) {
                $ids[$heroId] = true;
            }
        }

        $selectedId = $selectedMemorial?->getOriginalHeroId();
        if (null !== $selectedId && $selectedId > 0) {
            $ids[$selectedId] = true;
        }

        if ([] === $ids) {
            return [];
        }

        $heroes = $this->heroRepository->findBy([
            'id' => array_keys($ids),
            'team' => $team,
        ]);

        $present = [];
        foreach ($heroes as $hero) {
            $heroId = $hero->getId();
            if (null !== $heroId) {
                $present[$heroId] = true;
            }
        }

        return $present;
    }
}
