<?php

declare(strict_types=1);

namespace App\Tests\Service\Hero;

use App\Config\HeroRatingConfig;
use App\Entity\Hero\Hero;
use App\Entity\Team\Team;
use App\Enum\HeroRole;
use App\Enum\HeroStatus;
use App\Enum\Race;
use App\Service\Economy\EconomyService;
use App\Service\Economy\FinancialCrisisService;
use App\Service\Graveyard\GraveyardService;
use App\Service\Hero\HeroDismissalService;
use App\Service\Hero\HeroRatingCalculator;
use App\Service\Team\TeamChemistryService;
use App\Service\Team\TeamRosterService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class HeroDismissalServiceTest extends TestCase
{
    public function testDismissKeepsHeroAndMarksDead(): void
    {
        $graveyard = $this->createMock(GraveyardService::class);
        $graveyard->expects($this->once())->method('prepareHeroRemoval');
        $graveyard->expects($this->once())->method('recordMemorial');

        $ratingCalculator = $this->createMock(HeroRatingCalculator::class);
        $ratingCalculator->method('estimateGoldValue')->willReturn(0);

        $chemistry = $this->createMock(TeamChemistryService::class);
        $chemistry->expects($this->once())->method('recalculate');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $service = new HeroDismissalService(
            $this->createMock(TeamRosterService::class),
            $graveyard,
            $this->createMock(EconomyService::class),
            $this->createMock(FinancialCrisisService::class),
            $this->createMock(\App\Service\TeamChronicle\TeamChronicleService::class),
            $chemistry,
            $ratingCalculator,
            new HeroRatingConfig(dirname(__DIR__, 3)),
            $em,
        );

        $team = new Team();
        $hero = new Hero();
        $hero->setTeam($team);
        $hero->setName('Aldric');
        $hero->setRace(Race::Human);
        $hero->setRole(HeroRole::Combatant);
        $hero->setStatus(HeroStatus::Available);

        $service->dismiss($team, $hero);

        $this->assertSame(HeroStatus::Dead, $hero->getStatus());
    }
}
