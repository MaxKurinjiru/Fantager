<?php

declare(strict_types=1);

namespace App\Tests\Service\Training;

use App\Entity\Hero\Hero;
use App\Entity\Team\Team;
use App\Enum\HeroRole;
use App\Enum\HeroStatus;
use App\Enum\Race;
use App\Config\HeroRatingConfig;
use App\Service\Economy\EconomyService;
use App\Service\Economy\FinancialCrisisService;
use App\Service\Graveyard\GraveyardService;
use App\Service\Hero\HeroRatingCalculator;
use App\Service\TeamChronicle\TeamChronicleService;
use App\Exception\UserFacingException;
use App\Service\Training\TrainerDismissalService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class TrainerDismissalServiceTest extends TestCase
{
    public function testEstimateTrainerValueUsesRatingCalculator(): void
    {
        $ratingCalculator = $this->createMock(HeroRatingCalculator::class);
        $ratingCalculator->expects($this->once())
            ->method('estimateGoldValue')
            ->willReturn(420);

        $service = new TrainerDismissalService(
            $this->createMock(GraveyardService::class),
            $this->createMock(EconomyService::class),
            $this->createMock(FinancialCrisisService::class),
            $this->createMock(TeamChronicleService::class),
            $ratingCalculator,
            new HeroRatingConfig(dirname(__DIR__, 3)),
            $this->createMock(EntityManagerInterface::class),
        );

        $trainer = new Hero();
        $trainer->setRole(HeroRole::Trainer);

        $this->assertSame(420, $service->estimateTrainerValue($trainer));
    }

    public function testDismissRejectsNonActiveTrainer(): void
    {
        $service = new TrainerDismissalService(
            $this->createMock(GraveyardService::class),
            $this->createMock(EconomyService::class),
            $this->createMock(FinancialCrisisService::class),
            $this->createMock(TeamChronicleService::class),
            $this->createMock(HeroRatingCalculator::class),
            new HeroRatingConfig(dirname(__DIR__, 3)),
            $this->createMock(EntityManagerInterface::class),
        );

        $team = new Team();
        $trainer = new Hero();
        $trainer->setTeam($team);
        $trainer->setName('Coach');
        $trainer->setRace(Race::Human);
        $trainer->setRole(HeroRole::Trainer);
        $trainer->setStatus(HeroStatus::Selling);

        $this->expectException(UserFacingException::class);
        $this->expectExceptionMessage('error.trainer_only_active_dismiss');

        $service->dismiss($team, $trainer);
    }
}
