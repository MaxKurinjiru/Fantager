<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use App\Enum\LeagueSeasonStatus;
use App\Service\Formation\FixtureFormationService;
use App\Service\Formation\MatchLineupService;
use App\Service\League\LeagueService;
use App\Service\League\SeasonReportService;
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
        private readonly MatchLineupService $matchLineupService,
        private readonly UserMessageTranslator $userMessages,
        private readonly SeasonReportService $seasonReportService,
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

        $tab = (string) $request->query->get('tab', 'standings');

        $kingdom = $team->getKingdom();
        $seasons = $this->leagueService->listBrowsableSeasons($kingdom);
        $seasonIdStr = $request->query->get('seasonId');
        $seasonId = null !== $seasonIdStr && '' !== $seasonIdStr && is_numeric($seasonIdStr)
            ? (int) $seasonIdStr
            : null;
        $season = $this->leagueService->resolveSeason($kingdom, $seasonId);

        if (null === $season) {
            return $this->render('league/index.html.twig', [
                'team' => $team,
                'kingdom' => $kingdom,
                'tab' => $tab,
                'season' => null,
                'seasons' => [],
                'viewingArchive' => false,
                'seasonResult' => null,
                'groups' => [],
                'selectedGroup' => null,
                'standings' => [],
                'fixtures' => [],
                'myFixtures' => [],
                'globalLeaderboard' => [],
                'forms' => [],
                'fixtureFormations' => [],
                'fixtureLineups' => [],
                'yearbook' => null,
            ]);
        }

        $viewingArchive = LeagueSeasonStatus::Completed === $season->getStatus();
        $allowedTabs = ['standings', 'my-fixtures', 'leaderboard'];
        if ($viewingArchive) {
            $allowedTabs[] = 'yearbook';
        }
        if (!in_array($tab, $allowedTabs, true)) {
            $tab = 'standings';
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
        $fixtureLineups = [];

        if (null !== $selectedGroup) {
            $standings = $this->leagueService->getSortedStandings($selectedGroup);
            $fixtures = $this->leagueService->getFixturesForGroup($selectedGroup);

            foreach ($standings as $standing) {
                $standingTeam = $standing->getTeam();
                $forms[$standingTeam->getId() ?? 0] = $this->leagueService->getTeamForm($standingTeam, $season);
            }

            $myFixtures = $this->leagueService->getTeamFixturesForSeason($season, $team);

            if (!$viewingArchive) {
                foreach ($myFixtures as $fixture) {
                    $fixtureId = $fixture->getId() ?? 0;
                    $fixtureFormations[$fixtureId] = $this->fixtureFormationService->getFormationSummary($fixture, $team);
                    if (!$fixture->isCompleted()) {
                        $fixtureLineups[$fixtureId] = $this->matchLineupService->preview(
                            $team,
                            $this->fixtureFormationService->getAssignedFormation($fixture, $team),
                        );
                    }
                }
            }

            $globalLeaderboard = $this->leagueService->getGlobalLeaderboard($season);
        }

        return $this->render('league/index.html.twig', [
            'team' => $team,
            'kingdom' => $kingdom,
            'tab' => $tab,
            'season' => $season,
            'seasons' => $seasons,
            'viewingArchive' => $viewingArchive,
            'seasonResult' => $viewingArchive ? $this->leagueService->summarizeSeasonResult($team, $season) : null,
            'groups' => $groups,
            'selectedGroup' => $selectedGroup,
            'standings' => $standings,
            'fixtures' => $fixtures,
            'myFixtures' => $myFixtures,
            'globalLeaderboard' => $globalLeaderboard,
            'forms' => $forms,
            'fixtureFormations' => $fixtureFormations,
            'fixtureLineups' => $fixtureLineups,
            'yearbook' => $viewingArchive ? $this->seasonReportService->yearbookForTeam($season, $team) : null,
        ]);
    }
}
