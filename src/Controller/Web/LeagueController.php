<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use App\Service\Formation\FixtureFormationService;
use App\Service\League\LeagueService;
use App\Service\Translation\UserMessageTranslator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_PLAYER')]
class LeagueController extends AbstractController
{
    public function __construct(
        private readonly LeagueService $leagueService,
        private readonly FixtureFormationService $fixtureFormationService,
        private readonly UserMessageTranslator $userMessages,
    ) {
    }

    #[Route('/app/league', name: 'app_league', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $team = $user->getTeam();

        if (!$team) {
            $this->addFlash('error', $this->userMessages->trans('error.no_team'));

            return $this->redirectToRoute('app_home');
        }

        $tab = $request->query->get('tab', 'standings');
        if (!in_array($tab, ['standings', 'my-fixtures', 'leaderboard'], true)) {
            $tab = 'standings';
        }

        $season = $this->leagueService->getCurrentSeason($team->getKingdom());

        if (null === $season) {
            return $this->render('league/index.html.twig', [
                'team' => $team,
                'kingdom' => $team->getKingdom(),
                'tab' => $tab,
                'season' => null,
                'groups' => [],
                'selectedGroup' => null,
                'standings' => [],
                'fixtures' => [],
                'myFixtures' => [],
                'globalLeaderboard' => [],
                'forms' => [],
                'fixtureFormations' => [],
            ]);
        }

        $groups = $this->leagueService->getGroupsForSeason($season);
        $userStanding = $this->leagueService->findStandingForTeam($season, $team);

        $groupIdStr = $request->query->get('groupId');
        $groupId = null !== $groupIdStr && '' !== $groupIdStr && is_numeric($groupIdStr)
            ? (int) $groupIdStr
            : null;

        $selectedGroup = $this->leagueService->resolveSelectedGroup($season, $userStanding, $groupId, $groups);

        $standings = [];
        $fixtures = [];
        $myFixtures = [];
        $globalLeaderboard = [];
        $forms = [];
        $fixtureFormations = [];

        if (null !== $selectedGroup) {
            $standings = $this->leagueService->getSortedStandings($selectedGroup);
            $fixtures = $this->leagueService->getFixturesForGroup($selectedGroup);

            foreach ($standings as $standing) {
                $standingTeam = $standing->getTeam();
                $forms[$standingTeam->getId() ?? 0] = $this->leagueService->getTeamForm($standingTeam, $season);
            }

            $myFixtures = $this->leagueService->getTeamFixturesForSeason($season, $team);

            foreach ($myFixtures as $fixture) {
                $fixtureFormations[$fixture->getId() ?? 0] = $this->fixtureFormationService->getFormationSummary($fixture, $team);
            }

            $globalLeaderboard = $this->leagueService->getGlobalLeaderboard($season);
        }

        return $this->render('league/index.html.twig', [
            'team' => $team,
            'kingdom' => $team->getKingdom(),
            'tab' => $tab,
            'season' => $season,
            'groups' => $groups,
            'selectedGroup' => $selectedGroup,
            'standings' => $standings,
            'fixtures' => $fixtures,
            'myFixtures' => $myFixtures,
            'globalLeaderboard' => $globalLeaderboard,
            'forms' => $forms,
            'fixtureFormations' => $fixtureFormations,
        ]);
    }
}
