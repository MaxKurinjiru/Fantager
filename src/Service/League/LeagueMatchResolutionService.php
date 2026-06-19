<?php

declare(strict_types=1);

namespace App\Service\League;

use App\Entity\Combat\Battle;
use App\Entity\Kingdom\Kingdom;
use App\Entity\League\LeagueFixture;
use App\Entity\League\LeagueGroup;
use App\Entity\League\LeagueStanding;
use App\Entity\Team\Team;
use App\Enum\MatchType;
use App\Repository\League\LeagueFixtureRepository;
use App\Repository\League\LeagueStandingRepository;
use App\Service\Combat\MatchSimulatorInterface;
use App\Service\Team\FanClubService;
use App\Service\Team\TeamRosterService;
use App\ValueObject\Combat\MatchOutcome;
use Doctrine\ORM\EntityManagerInterface;

class LeagueMatchResolutionService
{
    public function __construct(
        private readonly LeagueFixtureRepository $fixtureRepository,
        private readonly LeagueStandingRepository $standingRepository,
        private readonly TeamRosterService $teamRosterService,
        private readonly MatchSimulatorInterface $matchSimulator,
        private readonly LeagueStandingService $standingService,
        private readonly LeagueFixtureCompletionService $fixtureCompletionService,
        private readonly FanClubService $fanClubService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function processLeagueMatchTick(Kingdom $kingdom, \DateTimeImmutable $scheduledAt): array
    {
        $fixtures = $this->fixtureRepository->findScheduledFixturesAtTime($kingdom, $scheduledAt);
        $results = [];

        foreach ($fixtures as $fixture) {
            $results[] = $this->resolveFixture($fixture, $scheduledAt);
        }

        return $results;
    }

    /**
     * Resolve all scheduled fixtures whose kickoff is at or before $until.
     * Use after fixing fixture/tick time alignment to catch up missed match days.
     *
     * @return list<array<string, mixed>>
     */
    public function resolvePendingFixtures(Kingdom $kingdom, \DateTimeImmutable $until): array
    {
        $fixtures = $this->fixtureRepository->findPendingFixturesUntil($kingdom, $until);
        $results = [];

        foreach ($fixtures as $fixture) {
            $results[] = $this->resolveFixture($fixture, $fixture->getScheduledAt());
        }

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveFixture(LeagueFixture $fixture, \DateTimeImmutable $processedAt): array
    {
        $outcome = $this->resolveOutcome($fixture);
        $homeTeam = $fixture->getHomeTeam();
        $awayTeam = $fixture->getAwayTeam();

        $battle = $this->createBattle($fixture, $outcome, $processedAt);
        $this->em->persist($battle);

        $homeStanding = $this->requireStanding($fixture->getGroup(), $homeTeam);
        $awayStanding = $this->requireStanding($fixture->getGroup(), $awayTeam);

        $this->standingService->applyMatchResult(
            $homeStanding,
            $awayStanding,
            $outcome->getHomeScore(),
            $outcome->getAwayScore(),
        );

        $this->fanClubService->applyFixtureResult(
            $homeTeam,
            $awayTeam,
            $outcome->getHomeScore(),
            $outcome->getAwayScore(),
        );

        $this->fixtureCompletionService->complete($fixture, $battle);

        return [
            'fixture_id' => $fixture->getId(),
            'home_team_id' => $homeTeam->getId(),
            'away_team_id' => $awayTeam->getId(),
            'home_score' => $outcome->getHomeScore(),
            'away_score' => $outcome->getAwayScore(),
            'is_forfeit' => $outcome->isForfeit(),
            'battle_id' => $battle->getId(),
        ];
    }

    private function resolveOutcome(LeagueFixture $fixture): MatchOutcome
    {
        $forfeitOutcome = $this->resolveForfeitOutcome($fixture);
        if (null !== $forfeitOutcome) {
            return $forfeitOutcome;
        }

        return $this->matchSimulator->simulate($fixture);
    }

    private function resolveForfeitOutcome(LeagueFixture $fixture): ?MatchOutcome
    {
        $homeEligible = $this->isTeamEligible($fixture->getHomeTeam());
        $awayEligible = $this->isTeamEligible($fixture->getAwayTeam());

        if ($homeEligible && $awayEligible) {
            return null;
        }

        if ($homeEligible) {
            return MatchOutcome::forfeit(3, 0);
        }

        if ($awayEligible) {
            return MatchOutcome::forfeit(0, 3);
        }

        return MatchOutcome::forfeit(0, 0);
    }

    private function isTeamEligible(Team $team): bool
    {
        return $this->teamRosterService->countCombatReadyHeroes($team) >= TeamRosterService::MIN_COMBAT_READY_HEROES;
    }

    private function createBattle(
        LeagueFixture $fixture,
        MatchOutcome $outcome,
        \DateTimeImmutable $processedAt,
    ): Battle {
        $homeTeam = $fixture->getHomeTeam();
        $awayTeam = $fixture->getAwayTeam();

        $battle = new Battle();
        $battle->setKingdom($homeTeam->getKingdom());
        $battle->setMatchType(MatchType::League);
        $battle->setTeamA($homeTeam);
        $battle->setTeamB($awayTeam);
        $battle->setFormationA($fixture->getHomeFormation());
        $battle->setFormationB($fixture->getAwayFormation());
        $battle->setScoreA($outcome->getHomeScore());
        $battle->setScoreB($outcome->getAwayScore());
        $battle->setResult($outcome->toBattleResult());
        $battle->setCombatLog([
            'simulator' => $outcome->isForfeit() ? 'forfeit' : 'stub_random',
        ]);
        $battle->setProcessedAt($processedAt);

        return $battle;
    }

    private function requireStanding(LeagueGroup $group, Team $team): LeagueStanding
    {
        $standing = $this->standingRepository->findOneBy([
            'group' => $group,
            'team' => $team,
        ]);

        if (null === $standing) {
            throw new \RuntimeException(sprintf('League standing not found for team %d in group %d.', $team->getId(), $group->getId()));
        }

        return $standing;
    }
}
