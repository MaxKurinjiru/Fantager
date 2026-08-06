<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Controller\Api\ApiControllerTrait;
use App\Entity\Auth\User;
use App\Entity\Kingdom\Kingdom;
use App\Entity\League\LeagueFixture;
use App\Entity\League\LeagueGroup;
use App\Entity\League\LeagueSeason;
use App\Entity\Team\Team;
use App\Repository\Kingdom\KingdomRepository;
use App\Service\League\LeagueService;
use App\Service\League\SeasonTransitionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/league')]
#[IsGranted('ROLE_PLAYER')]
class LeagueController extends AbstractController
{
    use ApiControllerTrait;

    public function __construct(
        private readonly LeagueService $leagueService,
        private readonly SeasonTransitionService $seasonTransitionService,
        private readonly KingdomRepository $kingdomRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/standings', name: 'api_league_standings', methods: ['GET'])]
    public function standings(Request $request): JsonResponse
    {
        $team = $this->getPlayerTeam();
        $kingdom = $team?->getKingdom();

        $kingdomIdStr = $request->query->get('kingdomId');
        if (null !== $kingdomIdStr && is_numeric($kingdomIdStr)) {
            $foundKingdom = $this->kingdomRepository->find((int) $kingdomIdStr);
            if ($foundKingdom instanceof Kingdom) {
                $kingdom = $foundKingdom;
            }
        }

        if (null === $kingdom) {
            return $this->jsonError('error.kingdom_not_found', 404);
        }

        $seasonIdStr = $request->query->get('seasonId');
        $season = null;
        if (null !== $seasonIdStr && is_numeric($seasonIdStr)) {
            /** @var LeagueSeason|null $season */
            $season = $this->em->getRepository(LeagueSeason::class)->find((int) $seasonIdStr);
        }

        if (null === $season) {
            $season = $this->leagueService->getCurrentSeason($kingdom);
        }

        if (null === $season) {
            return $this->json([
                'season' => null,
                'groups' => [],
                'selected_group' => null,
                'standings' => [],
            ]);
        }

        $groups = $this->leagueService->getGroupsForSeason($season);
        $userStanding = $team ? $this->leagueService->findStandingForTeam($season, $team) : null;

        $groupIdStr = $request->query->get('groupId');
        $groupId = null !== $groupIdStr && is_numeric($groupIdStr) ? (int) $groupIdStr : null;

        $selectedGroup = $this->leagueService->resolveSelectedGroup($season, $userStanding, $groupId, $groups);

        $standingsData = [];
        if (null !== $selectedGroup) {
            $sortedStandings = $this->leagueService->getSortedStandings($selectedGroup);
            $rank = 1;
            foreach ($sortedStandings as $standing) {
                $standingTeam = $standing->getTeam();
                $form = $this->leagueService->getTeamForm($standingTeam, $season);

                $standingsData[] = [
                    'rank' => $rank++,
                    'standing_id' => $standing->getId(),
                    'team_id' => $standingTeam->getId(),
                    'team_name' => $standingTeam->getName(),
                    'emblem' => $standingTeam->getEmblem(),
                    'is_npc' => $standingTeam->isNpc(),
                    'played' => $standing->getPlayed(),
                    'won' => $standing->getWins(),
                    'drawn' => $standing->getDraws(),
                    'lost' => $standing->getLosses(),
                    'points' => $standing->getPoints(),
                    'goal_difference' => $standing->getGoalDifference(),
                    'form' => $form,
                ];
            }
        }

        $groupsPayload = array_map(static fn (LeagueGroup $g) => [
            'id' => $g->getId(),
            'tier_name' => $g->getTier()->getTierName(),
            'group_name' => $g->getGroupName(),
        ], $groups);

        return $this->json([
            'season' => [
                'id' => $season->getId(),
                'season_number' => $season->getSeasonNumber(),
                'year' => (int) $season->getStartDate()->format('Y'),
                'status' => $season->getStatus()->value,
            ],
            'groups' => $groupsPayload,
            'selected_group' => null !== $selectedGroup ? [
                'id' => $selectedGroup->getId(),
                'tier_name' => $selectedGroup->getTier()->getTierName(),
                'group_name' => $selectedGroup->getGroupName(),
            ] : null,
            'standings' => $standingsData,
        ]);
    }

    #[Route('/fixtures', name: 'api_league_fixtures', methods: ['GET'])]
    public function fixtures(Request $request): JsonResponse
    {
        $team = $this->getPlayerTeam();
        $groupIdStr = $request->query->get('groupId');
        $teamIdStr = $request->query->get('teamId');
        $seasonIdStr = $request->query->get('seasonId');

        $fixtures = [];

        if (null !== $groupIdStr && is_numeric($groupIdStr)) {
            /** @var LeagueGroup|null $group */
            $group = $this->em->getRepository(LeagueGroup::class)->find((int) $groupIdStr);
            if ($group instanceof LeagueGroup) {
                $fixtures = $this->leagueService->getFixturesForGroup($group);
            }
        } elseif (null !== $teamIdStr && is_numeric($teamIdStr)) {
            /** @var Team|null $targetTeam */
            $targetTeam = $this->em->getRepository(Team::class)->find((int) $teamIdStr);
            if ($targetTeam instanceof Team) {
                $season = null;
                if (null !== $seasonIdStr && is_numeric($seasonIdStr)) {
                    /** @var LeagueSeason|null $season */
                    $season = $this->em->getRepository(LeagueSeason::class)->find((int) $seasonIdStr);
                }
                if (null === $season) {
                    $season = $this->leagueService->getCurrentSeason($targetTeam->getKingdom());
                }
                if ($season instanceof LeagueSeason) {
                    $fixtures = $this->leagueService->getTeamFixturesForSeason($season, $targetTeam);
                }
            }
        } elseif (null !== $team) {
            $season = $this->leagueService->getCurrentSeason($team->getKingdom());
            if ($season instanceof LeagueSeason) {
                $fixtures = $this->leagueService->getTeamFixturesForSeason($season, $team);
            }
        }

        $payload = array_map(static function (LeagueFixture $f): array {
            $battle = $f->getBattle();

            return [
                'id' => $f->getId(),
                'group_id' => $f->getGroup()->getId(),
                'status' => $f->getStatus()->value,
                'scheduled_at' => $f->getScheduledAt()->format(\DateTimeInterface::ATOM),
                'home_team' => [
                    'id' => $f->getHomeTeam()->getId(),
                    'name' => $f->getHomeTeam()->getName(),
                    'emblem' => $f->getHomeTeam()->getEmblem(),
                ],
                'away_team' => [
                    'id' => $f->getAwayTeam()->getId(),
                    'name' => $f->getAwayTeam()->getName(),
                    'emblem' => $f->getAwayTeam()->getEmblem(),
                ],
                'home_score' => $battle?->getScoreA(),
                'away_score' => $battle?->getScoreB(),
                'battle_id' => $battle?->getId(),
            ];
        }, $fixtures);

        return $this->json($payload);
    }

    #[Route('/seasons', name: 'api_league_seasons', methods: ['GET'])]
    public function seasons(Request $request): JsonResponse
    {
        $team = $this->getPlayerTeam();
        $kingdom = $team?->getKingdom();

        $kingdomIdStr = $request->query->get('kingdomId');
        if (null !== $kingdomIdStr && is_numeric($kingdomIdStr)) {
            $foundKingdom = $this->kingdomRepository->find((int) $kingdomIdStr);
            if ($foundKingdom instanceof Kingdom) {
                $kingdom = $foundKingdom;
            }
        }

        if (null === $kingdom) {
            return $this->jsonError('error.kingdom_not_found', 404);
        }

        /** @var list<LeagueSeason> $seasons */
        $seasons = $this->em->getRepository(LeagueSeason::class)->findBy(
            ['kingdom' => $kingdom],
            ['seasonNumber' => 'DESC']
        );

        $payload = array_map(static fn (LeagueSeason $s) => [
            'id' => $s->getId(),
            'season_number' => $s->getSeasonNumber(),
            'year' => (int) $s->getStartDate()->format('Y'),
            'status' => $s->getStatus()->value,
            'starts_at' => $s->getStartDate()->format(\DateTimeInterface::ATOM),
            'ends_at' => $s->getEndDate()->format(\DateTimeInterface::ATOM),
        ], $seasons);

        return $this->json($payload);
    }

    #[Route('/process-season', name: 'api_league_process_season', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function processSeason(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent(), true) ?? [];
        $kingdomId = $body['kingdomId'] ?? $request->query->get('kingdomId');

        $kingdom = null;
        if (null !== $kingdomId && is_numeric($kingdomId)) {
            $kingdom = $this->kingdomRepository->find((int) $kingdomId);
        }

        if (null === $kingdom) {
            $team = $this->getPlayerTeam();
            $kingdom = $team?->getKingdom();
        }

        if (null === $kingdom) {
            return $this->jsonError('error.kingdom_not_found', 404);
        }

        try {
            $this->seasonTransitionService->executeTransition($kingdom);
        } catch (\Throwable $e) {
            return $this->jsonException($e, 400);
        }

        return $this->json([
            'success' => true,
            'message' => 'Season transition completed successfully.',
        ]);
    }

    private function getPlayerTeam(): ?Team
    {
        /** @var User|null $user */
        $user = $this->getUser();

        return $user?->getTeam();
    }
}
