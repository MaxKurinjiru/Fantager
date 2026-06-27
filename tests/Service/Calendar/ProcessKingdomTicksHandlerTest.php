<?php

declare(strict_types=1);

namespace App\Tests\Service\Calendar;

use App\Entity\Kingdom\Kingdom;
use App\Entity\Kingdom\KingdomTickLog;
use App\Enum\TickType;
use App\Message\ExecuteSingleTickHandler;
use App\Message\ExecuteSingleTickMessage;
use App\Repository\Kingdom\KingdomRepository;
use App\Repository\Kingdom\KingdomTickLogRepository;
use App\Repository\Hero\HeroRepository;
use App\Service\Training\TrainingService;
use App\Service\Economy\ArenaRevenueService;
use App\Service\Calendar\KingdomTickOrchestrator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class ProcessKingdomTicksHandlerTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Repository\Kingdom\KingdomTickLogRepository */
    private $tickLogRepositoryMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Repository\Hero\HeroRepository */
    private $heroRepositoryMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Service\Training\TrainingService */
    private $trainingServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Service\Economy\ArenaRevenueService */
    private $arenaRevenueServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Service\Team\FanClubService */
    private $fanClubServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Service\Team\TeamMoraleReputationService */
    private $teamMoraleReputationServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Service\Headquarters\HeadquartersService */
    private $hqServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Service\Economy\FinancialCrisisService */
    private $financialCrisisServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Service\Economy\RoyalTreasuryService */
    private $royalTreasuryServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\Doctrine\ORM\EntityManagerInterface */
    private $entityManagerMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\Psr\Log\LoggerInterface */
    private $loggerMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Service\League\SeasonTransitionService */
    private $seasonTransitionServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Service\Auth\PlayerInactivityService */
    private $playerInactivityServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Service\Formation\FixtureFormationService */
    private $fixtureFormationServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Service\Marketplace\MarketplaceService */
    private $marketplaceServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Service\League\LeagueMatchResolutionService */
    private $leagueMatchResolutionServiceMock;
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Service\Calendar\KingdomTickOrchestrator */
    private $orchestratorMock;
    private ExecuteSingleTickHandler $handler;

    protected function setUp(): void
    {
        $this->tickLogRepositoryMock = $this->createMock(KingdomTickLogRepository::class);
        $this->heroRepositoryMock = $this->createMock(HeroRepository::class);
        $this->trainingServiceMock = $this->createMock(TrainingService::class);
        $this->arenaRevenueServiceMock = $this->createMock(ArenaRevenueService::class);
        $this->fanClubServiceMock = $this->createMock(\App\Service\Team\FanClubService::class);
        $this->teamMoraleReputationServiceMock = $this->createMock(\App\Service\Team\TeamMoraleReputationService::class);
        $this->hqServiceMock = $this->createMock(\App\Service\Headquarters\HeadquartersService::class);
        $this->financialCrisisServiceMock = $this->createMock(\App\Service\Economy\FinancialCrisisService::class);
        $this->royalTreasuryServiceMock = $this->createMock(\App\Service\Economy\RoyalTreasuryService::class);
        $this->playerInactivityServiceMock = $this->createMock(\App\Service\Auth\PlayerInactivityService::class);
        $this->fixtureFormationServiceMock = $this->createMock(\App\Service\Formation\FixtureFormationService::class);
        $this->marketplaceServiceMock = $this->createMock(\App\Service\Marketplace\MarketplaceService::class);
        $this->leagueMatchResolutionServiceMock = $this->createMock(\App\Service\League\LeagueMatchResolutionService::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->seasonTransitionServiceMock = $this->createMock(\App\Service\League\SeasonTransitionService::class);
        $this->orchestratorMock = $this->createMock(KingdomTickOrchestrator::class);

        $this->handler = new ExecuteSingleTickHandler(
            $this->tickLogRepositoryMock,
            $this->heroRepositoryMock,
            $this->trainingServiceMock,
            $this->arenaRevenueServiceMock,
            $this->fanClubServiceMock,
            $this->teamMoraleReputationServiceMock,
            $this->hqServiceMock,
            $this->financialCrisisServiceMock,
            $this->royalTreasuryServiceMock,
            $this->playerInactivityServiceMock,
            $this->fixtureFormationServiceMock,
            $this->marketplaceServiceMock,
            $this->leagueMatchResolutionServiceMock,
            $this->entityManagerMock,
            $this->loggerMock,
            $this->seasonTransitionServiceMock,
            $this->createMock(\App\Service\TeamChronicle\TeamChronicleService::class),
            new \App\Service\Calendar\TickClock(),
            $this->createMock(\App\Service\Team\NpcSimulationService::class),
            $this->orchestratorMock,
        );
    }

    private function setupQueryBuilderMock(int $rowsUpdated): void
    {
        $queryBuilderMock = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $queryMock = $this->createMock(\Doctrine\ORM\Query::class);

        $this->entityManagerMock
            ->method('createQueryBuilder')
            ->willReturn($queryBuilderMock);

        $queryBuilderMock->method('update')->willReturnSelf();
        $queryBuilderMock->method('set')->willReturnSelf();
        $queryBuilderMock->method('where')->willReturnSelf();
        $queryBuilderMock->method('andWhere')->willReturnSelf();
        $queryBuilderMock->method('setParameter')->willReturnSelf();
        $queryBuilderMock->method('getQuery')->willReturn($queryMock);

        $queryMock->method('execute')->willReturn($rowsUpdated);
    }

    public function testHandlerHaltsOnFailure(): void
    {
        $kingdom = new Kingdom();
        $kingdom->setName('Test Kingdom');

        $team = new \App\Entity\Team\Team();
        $team->setKingdom($kingdom);

        $log = new KingdomTickLog();
        $log->setKingdom($kingdom);
        $log->setTickType(TickType::WeeklyTraining);
        $log->setScheduledAt(new \DateTimeImmutable('2026-06-12T10:00:00Z'));
        $log->setStatus('pending');
        $log->setTeam($team);

        $this->tickLogRepositoryMock
            ->method('find')
            ->willReturn($log);

        // Simulating training tick failure
        $this->trainingServiceMock
            ->method('processTrainingTick')
            ->willThrowException(new \RuntimeException('Database connection lost'));

        // One transaction starts to run the tick (then rolls back), and another to save the failure (then commits)
        $this->entityManagerMock
            ->expects($this->exactly(2))
            ->method('beginTransaction');

        $this->entityManagerMock
            ->expects($this->exactly(1))
            ->method('rollback');

        $this->entityManagerMock
            ->expects($this->exactly(1))
            ->method('commit');

        $this->setupQueryBuilderMock(1);

        // Orchestrator must NEVER be called on failure to halt execution
        $this->orchestratorMock
            ->expects($this->never())
            ->method('orchestrate');

        // Run the handler
        $message = new ExecuteSingleTickMessage(1);
        $this->handler->__invoke($message);

        // Assert log is set to failed
        $this->assertSame('failed', $log->getStatus());
        $this->assertStringContainsString('Database connection lost', (string) $log->getErrorMessage());
    }

    public function testHandlerProcessesSingleTickAndTriggersOrchestrator(): void
    {
        $kingdom = new Kingdom();
        $kingdom->setName('Test Kingdom');

        $team = new \App\Entity\Team\Team();
        $team->setKingdom($kingdom);

        $log = new KingdomTickLog();
        $log->setKingdom($kingdom);
        $log->setTickType(TickType::WeeklyTraining);
        $log->setScheduledAt(new \DateTimeImmutable('2026-06-12T10:00:00Z'));
        $log->setStatus('pending');
        $log->setTeam($team);

        $this->tickLogRepositoryMock
            ->method('find')
            ->willReturn($log);

        // First transaction starts, training tick succeeds, transaction commits
        $this->entityManagerMock
            ->expects($this->exactly(1))
            ->method('beginTransaction');

        $this->entityManagerMock
            ->expects($this->exactly(1))
            ->method('commit');

        $this->setupQueryBuilderMock(1);

        // Orchestrator MUST be called once on success
        $this->orchestratorMock
            ->expects($this->once())
            ->method('orchestrate')
            ->with($kingdom);

        // Run the handler
        $message = new ExecuteSingleTickMessage(1);
        $this->handler->__invoke($message);

        // Assert log is set to completed
        $this->assertSame('completed', $log->getStatus());
    }
}
