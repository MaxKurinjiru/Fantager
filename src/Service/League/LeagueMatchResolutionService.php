<?php

declare(strict_types=1);

namespace App\Service\League;

use App\Entity\Combat\Battle;
use App\Entity\Formation\Formation;
use App\Entity\Hero\Hero;
use App\Entity\Item\Item;
use App\Entity\Kingdom\Kingdom;
use App\Entity\League\LeagueFixture;
use App\Entity\League\LeagueGroup;
use App\Entity\League\LeagueStanding;
use App\Entity\Team\Team;
use App\Enum\BattleResult;
use App\Enum\BattleStatus;
use App\Enum\HeroStatus;
use App\Enum\LeagueFixtureStatus;
use App\Enum\MatchType;
use App\Enum\MemorialCause;
use App\Repository\Formation\FormationRepository;
use App\Repository\League\LeagueFixtureRepository;
use App\Repository\League\LeagueStandingRepository;
use App\Service\Combat\CombatEngine;
use App\Service\Combat\CombatMatchRequestBuilder;
use App\Service\Combat\CombatSeedGenerator;
use App\Service\Config\RaceConfig;
use App\Service\Graveyard\GraveyardService;
use App\Service\Hero\HeroChronicleService;
use App\Service\Team\FanClubService;
use App\Service\Team\TeamMoraleReputationService;
use App\Service\Team\TeamRosterService;
use App\Service\TeamChronicle\TeamChronicleService;
use App\ValueObject\Combat\MatchOutcome;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class LeagueMatchResolutionService
{
    public function __construct(
        private readonly LeagueFixtureRepository $fixtureRepository,
        private readonly LeagueStandingRepository $standingRepository,
        private readonly TeamRosterService $teamRosterService,
        private readonly LeagueStandingService $standingService,
        private readonly LeagueFixtureCompletionService $fixtureCompletionService,
        private readonly FanClubService $fanClubService,
        private readonly TeamMoraleReputationService $teamMoraleReputationService,
        private readonly TeamChronicleService $teamChronicleService,
        private readonly \App\Service\Hero\HeroMasteryService $heroMasteryService,
        private readonly HeroChronicleService $heroChronicleService,
        private readonly FormationRepository $formationRepository,
        private readonly EntityManagerInterface $em,
        private readonly CombatMatchRequestBuilder $requestBuilder,
        private readonly CombatSeedGenerator $seedGenerator,
        private readonly CombatEngine $combatEngine,
        private readonly MessageBusInterface $messageBus,
        private readonly GraveyardService $graveyardService,
        private readonly RaceConfig $raceConfig,
        private readonly \App\Service\Notification\NotificationHelper $notificationHelper,
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
        $forfeitOutcome = $this->resolveForfeitOutcome($fixture);

        if (null !== $forfeitOutcome) {
            $homeTeam = $fixture->getHomeTeam();
            $awayTeam = $fixture->getAwayTeam();
            $battle = $this->createBattle($fixture, $forfeitOutcome, $processedAt);
            $battle->setStatus(BattleStatus::Completed);
            $this->em->persist($battle);

            $this->teamChronicleService->recordBattleOutcome(
                $homeTeam,
                $awayTeam,
                $forfeitOutcome->getHomeScore(),
                $forfeitOutcome->getAwayScore(),
                $battle
            );
            $this->teamChronicleService->recordBattleOutcome(
                $awayTeam,
                $homeTeam,
                $forfeitOutcome->getAwayScore(),
                $forfeitOutcome->getHomeScore(),
                $battle
            );

            $homeStanding = $this->requireStanding($fixture->getGroup(), $homeTeam);
            $awayStanding = $this->requireStanding($fixture->getGroup(), $awayTeam);

            $this->standingService->applyMatchResult(
                $homeStanding,
                $awayStanding,
                $forfeitOutcome->getHomeScore(),
                $forfeitOutcome->getAwayScore(),
            );

            $this->fanClubService->applyFixtureResult(
                $homeTeam,
                $awayTeam,
                $forfeitOutcome->getHomeScore(),
                $forfeitOutcome->getAwayScore(),
            );

            $this->teamMoraleReputationService->applyMatchResult(
                $homeTeam,
                $awayTeam,
                $forfeitOutcome,
                null,
                null,
            );

            $this->fixtureCompletionService->complete($fixture, $battle);

            return [
                'fixture_id' => $fixture->getId(),
                'home_team_id' => $homeTeam->getId(),
                'away_team_id' => $awayTeam->getId(),
                'home_score' => $forfeitOutcome->getHomeScore(),
                'away_score' => $forfeitOutcome->getAwayScore(),
                'is_forfeit' => true,
                'battle_id' => $battle->getId(),
            ];
        }

        // Both teams eligible -> start simulation wave orchestration
        $homeTeam = $fixture->getHomeTeam();
        $awayTeam = $fixture->getAwayTeam();

        $homeFormation = $fixture->getHomeFormation();
        if (null === $homeFormation) {
            $homeFormation = $this->formationRepository->findOneBy([
                'team' => $homeTeam,
                'isDefault' => true,
                'isTemporary' => false,
            ]);
        }

        $awayFormation = $fixture->getAwayFormation();
        if (null === $awayFormation) {
            $awayFormation = $this->formationRepository->findOneBy([
                'team' => $awayTeam,
                'isDefault' => true,
                'isTemporary' => false,
            ]);
        }

        if (!$homeFormation instanceof Formation) {
            throw new \RuntimeException(sprintf('Home team %d is missing a formation.', $homeTeam->getId()));
        }

        if (!$awayFormation instanceof Formation) {
            throw new \RuntimeException(sprintf('Away team %d is missing a formation.', $awayTeam->getId()));
        }

        $seed = $this->seedGenerator->forLeagueFixture($fixture);
        $request = $this->requestBuilder->fromFormations(
            $homeFormation,
            $awayFormation,
            MatchType::League,
            $seed,
        );

        $runState = $this->combatEngine->initializeRunState($request);

        $battle = new Battle();
        $battle->setKingdom($homeTeam->getKingdom());
        $battle->setMatchType(MatchType::League);
        $battle->setTeamA($homeTeam);
        $battle->setTeamB($awayTeam);
        $battle->setFormationA($homeFormation);
        $battle->setFormationB($awayFormation);
        $battle->setScoreA(0);
        $battle->setScoreB(0);
        $battle->setStatus(BattleStatus::Simulating);
        $battle->setCurrentRound(0);
        $battle->setRunState($runState);
        $battle->setScheduledAt($processedAt);

        $this->em->persist($battle);

        $fixture->setBattle($battle);
        $fixture->setStatus(LeagueFixtureStatus::InProgress);

        $this->em->flush();

        // Check if cohort initialization is complete
        $totalScheduledFixtures = (int) $this->em->createQueryBuilder()
            ->select('COUNT(f.id)')
            ->from(LeagueFixture::class, 'f')
            ->join('f.group', 'g')
            ->join('g.tier', 't')
            ->join('t.season', 's')
            ->where('s.kingdom = :kingdom')
            ->andWhere('f.scheduledAt = :scheduledAt')
            ->setParameter('kingdom', $homeTeam->getKingdom())
            ->setParameter('scheduledAt', $processedAt)
            ->getQuery()
            ->getSingleScalarResult();

        $initializedBattles = (int) $this->em->createQueryBuilder()
            ->select('COUNT(b.id)')
            ->from(Battle::class, 'b')
            ->where('b.kingdom = :kingdom')
            ->andWhere('b.scheduledAt = :scheduledAt')
            ->setParameter('kingdom', $homeTeam->getKingdom())
            ->setParameter('scheduledAt', $processedAt)
            ->getQuery()
            ->getSingleScalarResult();

        if ($totalScheduledFixtures === $initializedBattles) {
            $this->messageBus->dispatch(new \App\Message\CombatWave((int) $homeTeam->getKingdom()->getId(), $processedAt, 1));
        }

        return [
            'fixture_id' => $fixture->getId(),
            'home_team_id' => $homeTeam->getId(),
            'away_team_id' => $awayTeam->getId(),
            'home_score' => 0,
            'away_score' => 0,
            'is_forfeit' => false,
            'battle_id' => $battle->getId(),
        ];
    }

    public function completeBattle(Battle $battle): void
    {
        $fixture = $this->em->getRepository(LeagueFixture::class)->findOneBy(['battle' => $battle]);
        if (null === $fixture) {
            throw new \RuntimeException(sprintf('Fixture not found for battle ID %d.', $battle->getId()));
        }

        $homeTeam = $fixture->getHomeTeam();
        $awayTeam = $fixture->getAwayTeam();
        $homeFormation = $battle->getFormationA();
        $awayFormation = $battle->getFormationB();
        $battleResult = $battle->getResult();

        // 1. Process Hero Mastery participation
        if (null !== $homeFormation) {
            foreach ($homeFormation->getSlots() as $slot) {
                $hero = $slot->getHero();
                if (null !== $hero) {
                    $this->heroMasteryService->processMatchParticipation($hero);
                    $hero->setMatchesPlayed($hero->getMatchesPlayed() + 1);
                    $resultStr = 'draw';
                    if (BattleResult::WinA === $battleResult) {
                        $hero->setMatchesWon($hero->getMatchesWon() + 1);
                        $resultStr = 'win';
                    } elseif (BattleResult::WinB === $battleResult) {
                        $resultStr = 'loss';
                    }
                    $this->heroChronicleService->recordMatchPlayed($hero, $awayTeam, $resultStr, 0);
                }
            }
        }
        if (null !== $awayFormation) {
            foreach ($awayFormation->getSlots() as $slot) {
                $hero = $slot->getHero();
                if (null !== $hero) {
                    $this->heroMasteryService->processMatchParticipation($hero);
                    $hero->setMatchesPlayed($hero->getMatchesPlayed() + 1);
                    $resultStr = 'draw';
                    if (BattleResult::WinB === $battleResult) {
                        $hero->setMatchesWon($hero->getMatchesWon() + 1);
                        $resultStr = 'win';
                    } elseif (BattleResult::WinA === $battleResult) {
                        $resultStr = 'loss';
                    }
                    $this->heroChronicleService->recordMatchPlayed($hero, $homeTeam, $resultStr, 0);
                }
            }
        }

        // 2. Team Chronicle
        $this->teamChronicleService->recordBattleOutcome(
            $homeTeam,
            $awayTeam,
            $battle->getScoreA(),
            $battle->getScoreB(),
            $battle
        );
        $this->teamChronicleService->recordBattleOutcome(
            $awayTeam,
            $homeTeam,
            $battle->getScoreB(),
            $battle->getScoreA(),
            $battle
        );

        // 3. Standing
        $homeStanding = $this->requireStanding($fixture->getGroup(), $homeTeam);
        $awayStanding = $this->requireStanding($fixture->getGroup(), $awayTeam);

        $this->standingService->applyMatchResult(
            $homeStanding,
            $awayStanding,
            $battle->getScoreA(),
            $battle->getScoreB(),
        );

        // 4. Fan club
        $this->fanClubService->applyFixtureResult(
            $homeTeam,
            $awayTeam,
            $battle->getScoreA(),
            $battle->getScoreB(),
        );

        // 5. Morale
        $outcome = new MatchOutcome(
            $battle->getScoreA(),
            $battle->getScoreB(),
            false,
            $battle->getCombatLog(),
            $battle->getCombatLog()['seed'] ?? null
        );
        $this->teamMoraleReputationService->applyMatchResult(
            $homeTeam,
            $awayTeam,
            $outcome,
            $homeFormation,
            $awayFormation,
        );

        // Collect participating heroes
        $participatingHeroes = [];
        if (null !== $homeFormation) {
            foreach ($homeFormation->getSlots() as $slot) {
                if (null !== $slot->getHero()) {
                    $participatingHeroes[] = $slot->getHero();
                }
            }
        }
        if (null !== $awayFormation) {
            foreach ($awayFormation->getSlots() as $slot) {
                if (null !== $slot->getHero()) {
                    $participatingHeroes[] = $slot->getHero();
                }
            }
        }

        $rounds = $battle->getCurrentRound();
        $combatLog = $battle->getCombatLog();
        $resultData = $combatLog['result'] ?? [];
        $killedHeroIds = $resultData['killed_hero_ids'] ?? [];
        $itemHitCounts = $resultData['item_hit_counts'] ?? [];

        // 7. Durability loss (run before combat deaths so dead heroes' items are still equipped and can be updated)
        foreach ($participatingHeroes as $hero) {
            $heroId = $hero->getId();
            if (null === $heroId) {
                continue;
            }
            $hitsReceived = (int) ($itemHitCounts[(string) $heroId] ?? 0);
            $loss = (int) (floor($rounds / 10) + floor($hitsReceived / 3));
            $loss = max(1, min(20, $loss));

            $equippedItems = $this->em->getRepository(Item::class)->findBy(['equippedHero' => $hero]);
            foreach ($equippedItems as $item) {
                $newDurability = max(0, $item->getDurability() - $loss);
                $item->setDurability($newDurability);
            }
        }

        // 8. Process combat deaths and aging
        if (!empty($killedHeroIds)) {
            $deathCounts = array_count_values(array_map('intval', $killedHeroIds));
            foreach ($deathCounts as $heroId => $deathsInMatch) {
                $hero = $this->em->find(Hero::class, $heroId);
                if (!$hero instanceof Hero) {
                    continue;
                }

                // Escalating age penalty: 1st death = +1 year, 2nd = +2 years, etc.
                // Sum of 1 to D = D * (D + 1) / 2
                $yearsToAdd = (int) ($deathsInMatch * ($deathsInMatch + 1) / 2);
                $hero->setAgeRaw($hero->getAgeRaw() + ($yearsToAdd * 10));

                $isElder = $this->raceConfig->isAtOrAboveMortalityThreshold($hero->getRace(), $hero->getAge());
                $diedPermanently = false;

                if ($isElder) {
                    $threshold = $this->raceConfig->getMortalityThreshold($hero->getRace());
                    $yearsAboveThreshold = $hero->getAge() - $threshold;

                    // Base 5% chance + 4% per year above the mortality threshold
                    $deathChance = 0.05 + (0.04 * max(0, $yearsAboveThreshold));
                    $deathChance = min(1.0, $deathChance);

                    // Multiple deaths in one match each trigger a separate mortality check
                    for ($i = 0; $i < $deathsInMatch; ++$i) {
                        $roll = random_int(0, 99);
                        if ($roll < (int) ($deathChance * 100)) {
                            $diedPermanently = true;
                            break;
                        }
                    }
                }

                if ($diedPermanently) {
                    $this->graveyardService->prepareCombatDeath($hero);
                    $this->graveyardService->recordMemorial($hero, $hero->getTeam(), MemorialCause::CombatDeath);
                    $hero->setStatus(HeroStatus::Dead);

                    if (null !== $hero->getTeam()->getUser()) {
                        $opponent = $hero->getTeam()->getId() === $homeTeam->getId() ? $awayTeam : $homeTeam;
                        $this->notificationHelper->sendTranslatedNotification(
                            $hero->getTeam()->getUser(),
                            \App\Enum\NotificationType::HeroDied,
                            'notification.hero_combat_death_title',
                            'notification.hero_combat_death_body',
                            [],
                            ['%hero_name%' => $hero->getName(), '%opponent%' => $opponent->getName()]
                        );
                    }
                }
            }
        }

        // Send match result notifications to managers
        if (null !== $homeTeam->getUser()) {
            $this->notificationHelper->sendTranslatedNotification(
                $homeTeam->getUser(),
                \App\Enum\NotificationType::BattleResult,
                'notification.match_result_title',
                'notification.match_result_body',
                [],
                [
                    '%home_team%' => $homeTeam->getName(),
                    '%away_team%' => $awayTeam->getName(),
                    '%home_score%' => $battle->getScoreA(),
                    '%away_score%' => $battle->getScoreB(),
                ]
            );
        }

        if (null !== $awayTeam->getUser()) {
            $this->notificationHelper->sendTranslatedNotification(
                $awayTeam->getUser(),
                \App\Enum\NotificationType::BattleResult,
                'notification.match_result_title',
                'notification.match_result_body',
                [],
                [
                    '%home_team%' => $homeTeam->getName(),
                    '%away_team%' => $awayTeam->getName(),
                    '%home_score%' => $battle->getScoreA(),
                    '%away_score%' => $battle->getScoreB(),
                ]
            );
        }

        // 6. Complete fixture status
        $this->fixtureCompletionService->complete($fixture, $battle);
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
        ?Formation $homeFormation = null,
        ?Formation $awayFormation = null,
    ): Battle {
        $homeTeam = $fixture->getHomeTeam();
        $awayTeam = $fixture->getAwayTeam();

        $battle = new Battle();
        $battle->setKingdom($homeTeam->getKingdom());
        $battle->setMatchType(MatchType::League);
        $battle->setTeamA($homeTeam);
        $battle->setTeamB($awayTeam);
        $battle->setFormationA($homeFormation);
        $battle->setFormationB($awayFormation);
        $battle->setScoreA($outcome->getHomeScore());
        $battle->setScoreB($outcome->getAwayScore());
        $battle->setResult($outcome->toBattleResult());
        $battle->setCombatLog($outcome->getCombatLog());
        $battle->setProcessedAt($processedAt);
        $battle->setScheduledAt($processedAt);

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
