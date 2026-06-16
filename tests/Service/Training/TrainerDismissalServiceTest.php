<?php

declare(strict_types=1);

namespace App\Tests\Service\Training;

use App\Entity\Hero\Hero;
use App\Entity\Team\Team;
use App\Enum\HeroRole;
use App\Enum\HeroStatus;
use App\Enum\Race;
use App\Service\Economy\EconomyService;
use App\Service\Economy\FinancialCrisisService;
use App\Service\Graveyard\GraveyardService;
use App\Service\Training\TrainerDismissalService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class TrainerDismissalServiceTest extends TestCase
{
    public function testEstimateTrainerValueUsesStatSum(): void
    {
        $service = new TrainerDismissalService(
            $this->createMock(GraveyardService::class),
            $this->createMock(EconomyService::class),
            $this->createMock(FinancialCrisisService::class),
            $this->createMock(EntityManagerInterface::class),
        );

        $trainer = new Hero();
        $trainer->setRole(HeroRole::Trainer);
        $trainer->setStrRaw(50);
        $trainer->setDexRaw(50);
        $trainer->setKonRaw(50);
        $trainer->setSpdRaw(50);
        $trainer->setIntelRaw(50);
        $trainer->setWilRaw(50);
        $trainer->setChaRaw(50);
        $trainer->setLckRaw(50);

        $this->assertSame(120, $service->estimateTrainerValue($trainer));
    }

    public function testDismissRejectsNonActiveTrainer(): void
    {
        $service = new TrainerDismissalService(
            $this->createMock(GraveyardService::class),
            $this->createMock(EconomyService::class),
            $this->createMock(FinancialCrisisService::class),
            $this->createMock(EntityManagerInterface::class),
        );

        $team = new Team();
        $trainer = new Hero();
        $trainer->setTeam($team);
        $trainer->setName('Coach');
        $trainer->setRace(Race::Human);
        $trainer->setRole(HeroRole::Trainer);
        $trainer->setStatus(HeroStatus::Selling);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only active trainers can be dismissed.');

        $service->dismiss($team, $trainer);
    }
}
