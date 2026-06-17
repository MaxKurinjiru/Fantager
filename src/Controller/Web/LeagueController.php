<?php

declare(strict_types=1);

namespace App\Controller\Web;

use App\Entity\Auth\User;
use App\Entity\League\LeagueFixture;
use App\Entity\League\LeagueGroup;
use App\Entity\League\LeagueStanding;
use App\Service\Formation\FixtureFormationService;
use App\Service\League\LeagueService;
use App\Service\Translation\UserMessageTranslator;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly EntityManagerInterface $em,
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
        if (!in_array($tab, ['standings', 'fixtures', 'leaderboard', 'calendar'], true)) {
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

        // Fetch all groups in the active season, sorted by tier
        $groups = $this->em->getRepository(LeagueGroup::class)->createQueryBuilder('g')
            ->join('g.tier', 't')
            ->where('t.season = :season')
            ->setParameter('season', $season)
            ->orderBy('t.id', 'ASC')
            ->addOrderBy('g.groupName', 'ASC')
            ->getQuery()
            ->getResult();

        // Find the user's team's standing in the current season
        /** @var LeagueStanding|null $userStanding */
        $userStanding = $this->em->getRepository(LeagueStanding::class)->createQueryBuilder('ls')
            ->join('ls.group', 'g')
            ->join('g.tier', 't')
            ->where('t.season = :season')
            ->andWhere('ls.team = :team')
            ->setParameter('season', $season)
            ->setParameter('team', $team)
            ->getQuery()
            ->getOneOrNullResult();

        $userGroup = $userStanding ? $userStanding->getGroup() : null;

        // Check if a specific group is selected via request
        $groupIdStr = $request->query->get('groupId');
        $selectedGroup = null;

        if (null !== $groupIdStr && '' !== $groupIdStr && is_numeric($groupIdStr)) {
            /** @var LeagueGroup|null $selectedGroup */
            $selectedGroup = $this->em->getRepository(LeagueGroup::class)->find((int) $groupIdStr);
            // Verify group belongs to the active season
            if ($selectedGroup && $selectedGroup->getTier()->getSeason() !== $season) {
                $selectedGroup = null;
            }
        }

        if (null === $selectedGroup) {
            $selectedGroup = $userGroup;
        }

        if (null === $selectedGroup && !empty($groups)) {
            $selectedGroup = $groups[0];
        }

        $standings = [];
        $fixtures = [];
        $myFixtures = [];
        $globalLeaderboard = [];
        $forms = [];
        $fixtureFormations = [];

        if (null !== $selectedGroup) {
            $standings = $this->leagueService->getSortedStandings($selectedGroup);
            $fixtures = $this->em->getRepository(LeagueFixture::class)->findBy(
                ['group' => $selectedGroup],
                ['scheduledAt' => 'ASC']
            );

            // Get team forms for this group's standings
            foreach ($standings as $standing) {
                $t = $standing->getTeam();
                $forms[$t->getId() ?? 0] = $this->leagueService->getTeamForm($t, $season);
            }

            // Get user's own fixtures for the active season
            $myFixtures = $this->em->getRepository(LeagueFixture::class)->createQueryBuilder('lf')
                ->join('lf.group', 'g')
                ->join('g.tier', 't')
                ->where('t.season = :season')
                ->andWhere('(lf.homeTeam = :team OR lf.awayTeam = :team)')
                ->setParameter('season', $season)
                ->setParameter('team', $team)
                ->orderBy('lf.scheduledAt', 'ASC')
                ->getQuery()
                ->getResult();

            foreach ($myFixtures as $fixture) {
                $fixtureFormations[$fixture->getId() ?? 0] = $this->fixtureFormationService->getFormationSummary($fixture, $team);
            }

            // Get global leaderboard
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
