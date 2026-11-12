<?php

declare(strict_types=1);

namespace App\Tests\Service\League;

use App\Entity\Combat\Battle;
use App\Entity\Kingdom\Kingdom;
use App\Entity\League\LeagueFixture;
use App\Entity\League\LeagueGroup;
use App\Entity\League\LeagueSeason;
use App\Entity\League\LeagueTier;
use App\Entity\Marketplace\MarketplaceTransaction;
use App\Entity\Team\Team;
use App\Enum\BattleResult;
use App\Enum\LeagueFixtureStatus;
use App\Enum\MatchType;
use App\Enum\TransactionType;
use App\Service\League\SeasonReportBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SeasonReportBuilderTest extends KernelTestCase
{
    public function testMarketTransactionsAreGroupedByGameSeason(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get('doctrine.orm.entity_manager');
        $builder = $container->get(SeasonReportBuilder::class);
        $firstDate = new \DateTimeImmutable('2027-01-24 23:00:00', new \DateTimeZone('UTC'));
        $secondDate = new \DateTimeImmutable('2027-01-25 01:00:00', new \DateTimeZone('UTC'));

        $em->beginTransaction();
        try {
            $kingdom = (new Kingdom())->setName('Season market time test')->setLanguage('en')->setTimezone('UTC');
            $team = (new Team())->setKingdom($kingdom)->setName('Market time test team');
            $firstSeason = (new LeagueSeason())->setKingdom($kingdom)->setSeasonNumber(1)->setStartDate($firstDate)->setEndDate($firstDate);
            $secondSeason = (new LeagueSeason())->setKingdom($kingdom)->setSeasonNumber(2)->setStartDate($secondDate)->setEndDate($secondDate);
            foreach ([$kingdom, $team, $firstSeason, $secondSeason] as $entity) {
                $em->persist($entity);
            }
            foreach ([[$firstDate, 100], [$secondDate, 200]] as [$date, $amount]) {
                $transaction = (new MarketplaceTransaction($date))->setBuyerTeam($team)->setAmount($amount)
                    ->setFeeAmount(0)->setType(TransactionType::BuyNow);
                $em->persist($transaction);
            }
            $em->flush();

            self::assertSame(['transaction_count' => 1, 'volume_gold' => 100, 'fee_gold' => 0], $builder->build($firstSeason, [])['market']);
            self::assertSame(['transaction_count' => 1, 'volume_gold' => 200, 'fee_gold' => 0], $builder->build($secondSeason, [])['market']);
        } finally {
            $em->rollback();
            $em->clear();
        }
    }

    public function testCombatSummaryDoesNotHydrateBattleSnapshots(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        /** @var SeasonReportBuilder $builder */
        $builder = $container->get(SeasonReportBuilder::class);
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        $em->beginTransaction();
        try {
            $kingdom = (new Kingdom())->setName('Season report test')->setLanguage('en')->setTimezone('UTC');
            $team = (new Team())->setKingdom($kingdom)->setName('Report test team');
            $season = (new LeagueSeason())->setKingdom($kingdom)->setSeasonNumber(1)->setStartDate($now)->setEndDate($now);
            $tier = (new LeagueTier())->setSeason($season)->setTierName('Test')->setPromotionSlots(0)->setRelegationSlots(0);
            $group = (new LeagueGroup())->setTier($tier)->setGroupName('Test');
            foreach ([$kingdom, $team, $season, $tier, $group] as $entity) {
                $em->persist($entity);
            }

            foreach ([BattleResult::WinA, BattleResult::WinB, BattleResult::Draw, null] as $result) {
                $fixture = (new LeagueFixture())->setGroup($group)->setHomeTeam($team)->setAwayTeam($team)
                    ->setScheduledAt($now)->setStatus(LeagueFixtureStatus::Completed);
                if (null !== $result) {
                    $battle = (new Battle())->setKingdom($kingdom)->setMatchType(MatchType::League)
                        ->setTeamA($team)->setTeamB($team)->setResult($result)->setScoreA(2)->setScoreB(1)
                        ->setCurrentRound(BattleResult::Draw === $result ? 200 : 10)
                        ->setCombatLog(BattleResult::WinB === $result ? ['simulator' => 'forfeit'] : [])
                        ->setRunState(['seed' => 1, 'events' => array_fill(0, 100, 'event')]);
                    $em->persist($battle);
                    $fixture->setBattle($battle);
                }
                $em->persist($fixture);
            }
            $em->persist((new LeagueFixture())->setGroup($group)->setHomeTeam($team)->setAwayTeam($team)->setScheduledAt($now));
            $em->flush();
            $seasonId = $season->getId();
            $em->clear();

            $season = $em->find(LeagueSeason::class, $seasonId);
            self::assertNotNull($season);
            $payload = $builder->build($season, []);
            self::assertSame([
                'matches' => 4,
                'mean_kill_score' => 3.0,
                'round_200_share' => 0.25,
                'home_win_rate' => 0.25,
                'away_win_rate' => 0.25,
                'draw_rate' => 0.25,
                'forfeit_rate' => 0.25,
            ], $payload['combat']);
            self::assertArrayNotHasKey(Battle::class, $em->getUnitOfWork()->getIdentityMap());
        } finally {
            $em->rollback();
            $em->clear();
        }
    }
}
