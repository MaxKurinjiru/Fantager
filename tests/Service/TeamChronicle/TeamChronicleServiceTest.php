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
}
