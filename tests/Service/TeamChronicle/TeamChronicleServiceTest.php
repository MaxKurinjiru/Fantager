<?php

declare(strict_types=1);

namespace App\Tests\Service\TeamChronicle;

use App\Entity\Team\TeamChronicle;
use App\Entity\Auth\User;
use App\Entity\Kingdom\Kingdom;
use App\Entity\Team\Team;
use App\Enum\ChronicleReleaseReason;
use App\Enum\ChronicleEventType;
use App\Enum\Race;
use App\Service\TeamChronicle\TeamChronicleService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class TeamChronicleServiceTest extends TestCase
{
    public function testRecordPlayerJoinedPersistsExpectedEntry(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (TeamChronicle $log): bool {
                $this->assertSame(ChronicleEventType::PlayerJoined, $log->getType());
                $this->assertSame('activity.player_joined', $log->getSubjectKey());
                $this->assertSame(['player' => 'Alice'], $log->getSubjectParams());

                return true;
            }));

        $team = new Team();
        $user = new User();
        $user->setDisplayName('Alice');

        $service = new TeamChronicleService($em, new \App\Service\Calendar\TickClock());
        $service->recordPlayerJoined($team, $user);
    }

    public function testRecordPlayerReleasedUsesReasonSpecificSubjectKey(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (TeamChronicle $log): bool {
                $this->assertSame(ChronicleEventType::PlayerReleased, $log->getType());
                $this->assertSame('activity.player_released.bankruptcy', $log->getSubjectKey());
                $this->assertSame(['user_id' => null, 'reason' => 'bankruptcy'], $log->getData());

                return true;
            }));

        $team = new Team();
        $user = new User();
        $user->setDisplayName('Bob');

        $service = new TeamChronicleService($em, new \App\Service\Calendar\TickClock());
        $service->recordPlayerReleased($team, $user, ChronicleReleaseReason::Bankruptcy);
    }

    public function testRecordTeamEstablishedStoresKingdomContext(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');

        $kingdom = new Kingdom();
        $kingdom->setName('Test Kingdom');
        $team = new Team();

        $service = new TeamChronicleService($em, new \App\Service\Calendar\TickClock());
        $log = $service->recordTeamEstablished($team, $kingdom, 1);

        $this->assertSame(ChronicleEventType::TeamEstablished, $log->getType());
        $this->assertSame('Test Kingdom', $log->getSubjectParams()['kingdom']);
        $this->assertSame(1, $log->getData()['season']);
    }

    public function testRecordSeasonEndedNormalizesMaintainedStatusParam(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');

        $team = new Team();
        $service = new TeamChronicleService($em, new \App\Service\Calendar\TickClock());
        $log = $service->recordSeasonEnded($team, 2, 'T1', 4, '', 1000);

        $this->assertSame('maintained', $log->getSubjectParams()['status']);
        $this->assertSame(1000, $log->getData()['gold']);
    }

    public function testRecordSummonCompletedStoresHeroAndRace(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');

        $team = new Team();
        $hero = new \App\Entity\Hero\Hero();
        $hero->setName('Thorin');

        $service = new TeamChronicleService($em, new \App\Service\Calendar\TickClock());
        $log = $service->recordSummonCompleted($team, $hero, Race::Dwarf, 750);

        $this->assertSame(ChronicleEventType::SummonCompleted, $log->getType());
        $this->assertSame('Thorin', $log->getSubjectParams()['hero']);
        $this->assertSame(750, $log->getData()['gold_cost']);
    }

    public function testTeamChronicleUsesTickClockCustomTime(): void
    {
        $clock = new \App\Service\Calendar\TickClock();
        $customTime = new \DateTimeImmutable('2026-06-12 18:00:00', new \DateTimeZone('UTC'));
        $clock->setCustomTime($customTime);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (TeamChronicle $log) use ($customTime) {
                return $log->getCreatedAt() === $customTime;
            }));

        $team = new Team();
        $user = new User();
        $user->setDisplayName('Alice');

        $service = new TeamChronicleService($em, $clock);
        $service->recordPlayerJoined($team, $user);
    }

    public function testRecordHeroDismissedPersistsExpectedEntry(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (TeamChronicle $log): bool {
                $this->assertSame(ChronicleEventType::HeroDismissed, $log->getType());
                $this->assertSame('activity.hero_dismissed', $log->getSubjectKey());
                $this->assertSame(['hero' => 'Thorin', 'compensation' => '200'], $log->getSubjectParams());
                $this->assertSame(['hero_id' => 123, 'compensation' => 200], $log->getData());

                return true;
            }));

        $team = new Team();
        $hero = new \App\Entity\Hero\Hero();
        $hero->setName('Thorin');
        $reflection = new \ReflectionClass($hero);
        $idProp = $reflection->getProperty('id');
        $idProp->setValue($hero, 123);

        $service = new TeamChronicleService($em, new \App\Service\Calendar\TickClock());
        $service->recordHeroDismissed($team, $hero, 200);
    }

    public function testRecordTrainerDismissedPersistsExpectedEntry(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (TeamChronicle $log): bool {
                $this->assertSame(ChronicleEventType::TrainerDismissed, $log->getType());
                $this->assertSame('activity.trainer_dismissed', $log->getSubjectKey());
                $this->assertSame(['trainer' => 'Gandalf', 'compensation' => '150'], $log->getSubjectParams());
                $this->assertSame(['trainer_id' => 456, 'compensation' => 150], $log->getData());

                return true;
            }));

        $team = new Team();
        $trainer = new \App\Entity\Hero\Hero();
        $trainer->setName('Gandalf');
        $reflection = new \ReflectionClass($trainer);
        $idProp = $reflection->getProperty('id');
        $idProp->setValue($trainer, 456);

        $service = new TeamChronicleService($em, new \App\Service\Calendar\TickClock());
        $service->recordTrainerDismissed($team, $trainer, 150);
    }

    public function testRecordHeroPurchasedAndSoldPersistExpectedEntries(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->exactly(2))
            ->method('persist')
            ->with($this->callback(function (TeamChronicle $log): bool {
                if (ChronicleEventType::HeroPurchased === $log->getType()) {
                    $this->assertSame('activity.hero_purchased', $log->getSubjectKey());
                    $this->assertSame(['hero' => 'Thorin', 'race' => 'dwarf', 'seller' => 'Sellers', 'price' => '500'], $log->getSubjectParams());
                    $this->assertSame(['hero_id' => 123, 'seller_team_id' => 2, 'price' => 500], $log->getData());
                } else {
                    $this->assertSame(ChronicleEventType::HeroSold, $log->getType());
                    $this->assertSame('activity.hero_sold', $log->getSubjectKey());
                    $this->assertSame(['hero' => 'Thorin', 'race' => 'dwarf', 'buyer' => 'Buyers', 'price' => '500'], $log->getSubjectParams());
                    $this->assertSame(['hero_id' => 123, 'buyer_team_id' => 1, 'price' => 500], $log->getData());
                }

                return true;
            }));

        $buyer = new Team();
        $buyerReflection = new \ReflectionClass($buyer);
        $buyerId = $buyerReflection->getProperty('id');
        $buyerId->setValue($buyer, 1);
        $buyer->setName('Buyers');

        $seller = new Team();
        $sellerReflection = new \ReflectionClass($seller);
        $sellerId = $sellerReflection->getProperty('id');
        $sellerId->setValue($seller, 2);
        $seller->setName('Sellers');

        $hero = new \App\Entity\Hero\Hero();
        $hero->setName('Thorin');
        $hero->setRace(Race::Dwarf);
        $heroReflection = new \ReflectionClass($hero);
        $heroId = $heroReflection->getProperty('id');
        $heroId->setValue($hero, 123);

        $service = new TeamChronicleService($em, new \App\Service\Calendar\TickClock());
        $service->recordHeroPurchased($buyer, $hero, $seller, 500);
        $service->recordHeroSold($seller, $hero, $buyer, 500);
    }

    public function testRecordTeamRenamedPersistsExpectedEntry(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (TeamChronicle $log): bool {
                $this->assertSame(ChronicleEventType::TeamRenamed, $log->getType());
                $this->assertSame('activity.team_renamed', $log->getSubjectKey());
                $this->assertSame(['old_name' => 'Old Name', 'new_name' => 'New Name'], $log->getSubjectParams());
                $this->assertSame(['old_name' => 'Old Name', 'new_name' => 'New Name'], $log->getData());

                return true;
            }));

        $team = new Team();

        $service = new TeamChronicleService($em, new \App\Service\Calendar\TickClock());
        $service->recordTeamRenamed($team, 'Old Name', 'New Name');
    }
}
