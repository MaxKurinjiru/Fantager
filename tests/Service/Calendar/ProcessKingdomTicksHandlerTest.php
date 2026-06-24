<?php

declare(strict_types=1);

namespace App\Tests\Service\Calendar;

use App\Entity\Kingdom\Kingdom;
use App\Entity\Kingdom\KingdomTickLog;
use App\Enum\TickType;
use App\Message\ProcessKingdomTicksHandler;
use App\Message\ProcessKingdomTicksMessage;
use App\Repository\Kingdom\KingdomRepository;
use App\Repository\Kingdom\KingdomTickLogRepository;
use App\Repository\Hero\HeroRepository;
use App\Service\Training\TrainingService;
use App\Service\Economy\ArenaRevenueService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class ProcessKingdomTicksHandlerTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Repository\Kingdom\KingdomRepository */
    private $kingdomRepositoryMock;
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
    private ProcessKingdomTicksHandler $handler;

    protected function setUp(): void
    {
        $this->kingdomRepositoryMock = $this->createMock(KingdomRepository::class);
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

        $this->handler = new ProcessKingdomTicksHandler(
            $this->kingdomRepositoryMock,
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
        );
    }

    public function testHandlerHaltsOnFailure(): void
    {
        $kingdom = new Kingdom();
        $kingdom->setName('Test Kingdom');

        $log1 = new KingdomTickLog();
        $log1->setTickType(TickType::WeeklyTraining);
        $log1->setScheduledAt(new \DateTimeImmutable('2026-06-12T10:00:00Z'));
        $log1->setStatus('processing');

        $log2 = new KingdomTickLog();
        $log2->setTickType(TickType::LeagueMatch);
        $log2->setScheduledAt(new \DateTimeImmutable('2026-06-12T18:00:00Z'));
        $log2->setStatus('processing');

        $this->kingdomRepositoryMock
            ->method('find')
            ->willReturn($kingdom);

        $this->tickLogRepositoryMock
            ->method('findBy')
            ->willReturn([$log1, $log2]);

        // Simulating training tick failure
        $this->trainingServiceMock
            ->method('processTrainingTick')
            ->willThrowException(new \RuntimeException('Database connection lost'));

        // First transaction starts, training tick fails, transaction rolls back
        $this->entityManagerMock
            ->expects($this->exactly(2))
            ->method('beginTransaction');

        $this->entityManagerMock
            ->expects($this->exactly(1))
            ->method('rollback');

        // Second transaction starts to save failed log state, then commits
        $this->entityManagerMock
            ->expects($this->exactly(1))
            ->method('commit');

        // We expect log1 to be refreshed/retrieved during failure log phase
        $this->tickLogRepositoryMock
            ->method('find')
            ->willReturn($log1);

        // Run the handler
        $message = new ProcessKingdomTicksMessage(1);
        $this->handler->__invoke($message);

        // Assert log1 is set to failed
        $this->assertSame('failed', $log1->getStatus());
        $this->assertStringContainsString('Database connection lost', (string) $log1->getErrorMessage());

        // Assert log2 remains processing (halted execution, not touched!)
        $this->assertSame('processing', $log2->getStatus());
    }
}
