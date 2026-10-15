<?php

declare(strict_types=1);

namespace App\Service\FriendlyMatch;

use App\Entity\Combat\Battle;
use App\Entity\Formation\Formation;
use App\Entity\FriendlyMatch\FriendlyMatch;
use App\Entity\Hero\Hero;
use App\Entity\Item\Item;
use App\Entity\Team\Team;
use App\Enum\BattleResult;
use App\Enum\BattleStatus;
use App\Enum\FacilityType;
use App\Enum\FriendlyMatchStatus;
use App\Enum\HeroStatus;
use App\Enum\MatchType;
use App\Enum\MemorialCause;
use App\Enum\NotificationType;
use App\Repository\Formation\FormationRepository;
use App\Repository\FriendlyMatch\FriendlyMatchRepository;
use App\Repository\Headquarters\HeadquartersRepository;
use App\Service\Combat\CombatEngine;
use App\Service\Combat\CombatMatchRequestBuilder;
use App\Service\Combat\CombatSeedGenerator;
use App\Service\Config\RaceConfig;
use App\Service\Economy\FinancialCrisisService;
use App\Service\Graveyard\GraveyardService;
use App\Service\Hero\HeroChronicleService;
use App\Service\Hero\HeroMasteryService;
use App\Service\Notification\NotificationHelper;
use App\Service\Team\TeamRosterService;
use App\Service\TeamChronicle\TeamChronicleService;
use App\ValueObject\Combat\MatchOutcome;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Resolves friendly match kickoffs and completes battle side-effects.
 *
 * Mirrors LeagueMatchResolutionService but without:
 * - Arena ticket revenue
 * - League standings
 * - Fan club deltas
 * - Morale / reputation match deltas
 */
class FriendlyMatchResolutionService
{
    public function __construct(
        private readonly FriendlyMatchRepository $friendlyMatchRepository,
        private readonly FormationRepository $formationRepository,
        private readonly TeamRosterService $teamRosterService,
        private readonly TeamChronicleService $teamChronicleService,
        private readonly HeroMasteryService $heroMasteryService,
        private readonly HeroChronicleService $heroChronicleService,
        private readonly EntityManagerInterface $em,
        private readonly CombatMatchRequestBuilder $requestBuilder,
        private readonly CombatSeedGenerator $seedGenerator,
        private readonly CombatEngine $combatEngine,
        private readonly MessageBusInterface $messageBus,
        private readonly GraveyardService $graveyardService,
        private readonly RaceConfig $raceConfig,
        private readonly NotificationHelper $notificationHelper,
        private readonly HeadquartersRepository $headquartersRepository,
        private readonly FinancialCrisisService $financialCrisisService,
    ) {
    }

    /**
     * Kick off a scheduled friendly match: check eligibility, create battle, dispatch wave.
     *
     * @return array<string, mixed>
     */
    public function resolveMatch(FriendlyMatch $match, \DateTimeImmutable $processedAt): array
    {
        $homeTeam = $match->getHomeTeam();
        $awayTeam = $match->getAwayTeam();
        $kingdom = $match->getKingdom();

        $forfeitOutcome = $this->resolveForfeitOutcome($match);

        if (null !== $forfeitOutcome) {
            $battle = $this->createBattle($match, $forfeitOutcome, $processedAt);
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

            // No standings, fan club, or morale deltas for friendly matches
            $match->setBattle($battle);
            $match->setStatus(FriendlyMatchStatus::Completed);
            $this->em->flush();

            return [
                'friendly_match_id' => $match->getId(),
                'home_team_id' => $homeTeam->getId(),
                'away_team_id' => $awayTeam->getId(),
                'home_score' => $forfeitOutcome->getHomeScore(),
                'away_score' => $forfeitOutcome->getAwayScore(),
                'is_forfeit' => true,
                'battle_id' => $battle->getId(),
            ];
        }

        // Both teams eligible — resolve formations
        $homeFormation = $match->getHomeFormation();
        if (null === $homeFormation) {
            $homeFormation = $this->formationRepository->findOneBy([
                'team' => $homeTeam,
                'isDefault' => true,
                'isTemporary' => false,
            ]);
        }

        $awayFormation = $match->getAwayFormation();
        if (null === $awayFormation) {
            $awayFormation = $this->formationRepository->findOneBy([
                'team' => $awayTeam,
                'isDefault' => true,
                'isTemporary' => false,
            ]);
        }

        if (!$homeFormation instanceof Formation) {
            throw new \RuntimeException(sprintf('Home team %d is missing a formation for friendly match %d.', $homeTeam->getId(), $match->getId() ?? 0));
        }

        if (!$awayFormation instanceof Formation) {
            throw new \RuntimeException(sprintf('Away team %d is missing a formation for friendly match %d.', $awayTeam->getId(), $match->getId() ?? 0));
        }

        $seed = $this->seedGenerator->forFriendlyMatch($match);
        $request = $this->requestBuilder->fromFormations(
            $homeFormation,
            $awayFormation,
            MatchType::Friendly,
            $seed,
        );

        $runState = $this->combatEngine->initializeRunState($request);

        $battle = new Battle();
        $battle->setKingdom($kingdom);
        $battle->setMatchType(MatchType::Friendly);
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

        $match->setBattle($battle);
        $match->setStatus(FriendlyMatchStatus::InProgress);

        $this->em->flush();

        // Check whether all scheduled friendly matches at this time have been kicked off
        // If yes, dispatch the first CombatWave for this batch
        $scheduledCount = (int) $this->em->createQueryBuilder()
            ->select('COUNT(fm.id)')
            ->from(FriendlyMatch::class, 'fm')
            ->where('fm.kingdom = :kingdom')
            ->andWhere('fm.scheduledAt = :scheduledAt')
            ->andWhere('fm.status = :scheduled')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('scheduledAt', $processedAt)
            ->setParameter('scheduled', FriendlyMatchStatus::Scheduled)
            ->getQuery()
            ->getSingleScalarResult();

        $friendlyBattles = (int) $this->em->createQueryBuilder()
            ->select('COUNT(b.id)')
            ->from(Battle::class, 'b')
            ->where('b.kingdom = :kingdom')
            ->andWhere('b.scheduledAt = :scheduledAt')
            ->andWhere('b.matchType = :matchType')
            ->setParameter('kingdom', $kingdom)
            ->setParameter('scheduledAt', $processedAt)
            ->setParameter('matchType', MatchType::Friendly)
            ->getQuery()
            ->getSingleScalarResult();

        // scheduledCount=0 means this was the last one; friendlyBattles covers all real battles
        if (0 === $scheduledCount && $friendlyBattles > 0) {
            $this->messageBus->dispatch(new \App\Message\CombatWave((int) $kingdom->getId(), $processedAt, 1));
        }

        return [
            'friendly_match_id' => $match->getId(),
            'home_team_id' => $homeTeam->getId(),
            'away_team_id' => $awayTeam->getId(),
            'home_score' => 0,
            'away_score' => 0,
            'is_forfeit' => false,
            'battle_id' => $battle->getId(),
        ];
    }

    /**
     * Complete all side-effects after the battle simulation finishes.
     * Called from ProcessCombatRoundHandler when a Friendly-type battle is done.
     */
    public function completeBattle(Battle $battle): void
    {
        $match = $this->friendlyMatchRepository->findOneBy(['battle' => $battle]);
        if (null === $match) {
            throw new \RuntimeException(sprintf('FriendlyMatch not found for battle ID %d.', $battle->getId()));
        }

        $homeTeam = $match->getHomeTeam();
        $awayTeam = $match->getAwayTeam();
        $homeFormation = $battle->getFormationA();
        $awayFormation = $battle->getFormationB();
        $battleResult = $battle->getResult();

        // 1. Hero Mastery participation + Hero Chronicle
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

        // 3. NO standings, fan club, or morale deltas (friendly match rule)

        // Collect participating heroes for durability + death processing
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

        // 4. Durability loss (same as league)
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

        // 5. XP + fatigue + form (same as league)
        $killsByHeroId = $resultData['kills_by_hero_id'] ?? [];
        $killedSet = array_fill_keys(array_map('intval', $killedHeroIds), true);
        $xpTotal = 0;
        $xpTotal += $this->applySideRewards($homeFormation, $homeTeam, BattleResult::WinA === $battleResult, $killedSet, $killsByHeroId);
        $xpTotal += $this->applySideRewards($awayFormation, $awayTeam, BattleResult::WinB === $battleResult, $killedSet, $killsByHeroId);
        $battle->setXpAwarded($xpTotal);

        $rngState = (int) ($resultData['rng_state'] ?? $combatLog['seed'] ?? 1);
        if ($rngState <= 0) {
            $rngState = 1;
        }

        // 6. KO aging + permanent death (same as league)
        if (!empty($killedHeroIds)) {
            $rollsByHero = [];
            foreach ($killedHeroIds as $rawId) {
                $id = (int) $rawId;
                $rollsByHero[$id][] = CombatEngine::drawInt($rngState, 0, 99);
            }

            $deathCounts = array_count_values(array_map('intval', $killedHeroIds));
            foreach ($deathCounts as $heroId => $deathsInMatch) {
                $hero = $this->em->find(Hero::class, $heroId);
                if (!$hero instanceof Hero) {
                    continue;
                }

                $yearsToAdd = (int) ($deathsInMatch * ($deathsInMatch + 1) / 2);
                $hero->setAgeRaw($hero->getAgeRaw() + ($yearsToAdd * 10));

                $isElder = $this->raceConfig->isAtOrAboveMortalityThreshold($hero->getRace(), $hero->getAge());
                $diedPermanently = false;

                if ($isElder) {
                    $threshold = $this->raceConfig->getMortalityThreshold($hero->getRace());
                    $yearsAboveThreshold = $hero->getAge() - $threshold;

                    $deathChance = 0.05 + (0.04 * max(0, $yearsAboveThreshold));
                    $deathChance = min(1.0, $deathChance);

                    foreach ($rollsByHero[$heroId] ?? [] as $roll) {
                        if ($roll < (int) ($deathChance * 100)) {
                            $diedPermanently = true;
                            break;
                        }
                    }
                }

                if ($diedPermanently) {
                    $this->graveyardService->prepareCombatDeath($hero);
                    $this->graveyardService->recordMemorial($hero, $hero->getTeam(), MemorialCause::CombatDeath);
                    $this->teamChronicleService->recordHeroDied($hero->getTeam(), $hero, MemorialCause::CombatDeath->value);
                    $hero->setStatus(HeroStatus::Dead);

                    if (null !== $hero->getTeam()->getUser()) {
                        $opponent = $hero->getTeam()->getId() === $homeTeam->getId() ? $awayTeam : $homeTeam;
                        $this->notificationHelper->sendTranslatedNotification(
                            $hero->getTeam()->getUser(),
                            NotificationType::HeroDied,
                            'notification.hero_combat_death_title',
                            'notification.hero_combat_death_body',
                            [],
                            ['%hero_name%' => $hero->getName(), '%opponent%' => $opponent->getName()]
                        );
                    }
                }
            }
        }

        // 7. Match result notifications
        if (null !== $homeTeam->getUser()) {
            $this->notificationHelper->sendTranslatedNotification(
                $homeTeam->getUser(),
                NotificationType::BattleResult,
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
                NotificationType::BattleResult,
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

        // 8. Mark match completed; null out formations (cleanup)
        $match->setStatus(FriendlyMatchStatus::Completed);
        $match->setHomeFormation(null);
        $match->setAwayFormation(null);
    }

    private function resolveForfeitOutcome(FriendlyMatch $match): ?MatchOutcome
    {
        $homeEligible = $this->isTeamEligible($match->getHomeTeam());
        $awayEligible = $this->isTeamEligible($match->getAwayTeam());

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
        FriendlyMatch $match,
        MatchOutcome $outcome,
        \DateTimeImmutable $processedAt,
    ): Battle {
        $homeTeam = $match->getHomeTeam();
        $awayTeam = $match->getAwayTeam();

        $battle = new Battle();
        $battle->setKingdom($match->getKingdom());
        $battle->setMatchType(MatchType::Friendly);
        $battle->setTeamA($homeTeam);
        $battle->setTeamB($awayTeam);
        $battle->setFormationA(null);
        $battle->setFormationB(null);
        $battle->setScoreA($outcome->getHomeScore());
        $battle->setScoreB($outcome->getAwayScore());
        $battle->setResult($outcome->toBattleResult());
        $battle->setCombatLog($outcome->getCombatLog());
        $battle->setProcessedAt($processedAt);
        $battle->setScheduledAt($processedAt);

        return $battle;
    }

    /**
     * @param array<int, true>             $killedSet
     * @param array<string|int, int|mixed> $killsByHeroId
     */
    private function applySideRewards(
        ?Formation $formation,
        Team $team,
        bool $won,
        array $killedSet,
        array $killsByHeroId,
    ): int {
        if (null === $formation) {
            return 0;
        }

        $multiplier = $this->libraryXpMultiplier($team);
        $awarded = 0;

        foreach ($formation->getSlots() as $slot) {
            $hero = $slot->getHero();
            if (null === $hero) {
                continue;
            }
            $heroId = $hero->getId();
            if (null === $heroId) {
                continue;
            }

            $wasKo = isset($killedSet[$heroId]);
            $kills = (int) ($killsByHeroId[(string) $heroId] ?? $killsByHeroId[$heroId] ?? 0);

            $fatigue = $hero->getFatigue() + 15 + ($wasKo ? 10 : 0);
            $hero->setFatigue(min(100, $fatigue));

            $formLoss = 8 + ($wasKo ? 7 : 0);
            $hero->setForm(max(0, $hero->getForm() - $formLoss));

            $xp = (int) round((20 + ($won ? 15 : 0) + (10 * $kills)) * $multiplier);
            $hero->setXp($hero->getXp() + $xp);
            $awarded += $xp;
        }

        return $awarded;
    }

    private function libraryXpMultiplier(Team $team): float
    {
        $hq = $this->headquartersRepository->findOneBy(['team' => $team]);
        if (null === $hq || !$this->financialCrisisService->areHqBonusesActive($team)) {
            return 1.0;
        }

        foreach ($hq->getFacilities() as $facility) {
            if (FacilityType::Library === $facility->getType()) {
                $pct = (float) ($facility->getPassiveBonuses()['xp_gain_pct'] ?? 0);

                return 1.0 + ($pct / 100.0);
            }
        }

        return 1.0;
    }
}
