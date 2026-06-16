<?php

declare(strict_types=1);

namespace App\Tests\Service\Graveyard;

use App\Entity\Graveyard\GraveyardRecord;
use App\Entity\Hero\Hero;
use App\Entity\Team\Team;
use App\Enum\GraveyardCause;
use App\Enum\HeroStatus;
use App\Enum\Race;
use App\Service\Graveyard\GraveyardService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class GraveyardServiceTest extends TestCase
{
    public function testRecordHeroCreatesSnapshot(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(GraveyardRecord::class));

        $service = new GraveyardService($em);

        $team = new Team();
        $hero = new Hero();
        $hero->setName('Aldric');
        $hero->setRace(Race::Human);
        $hero->setLevel(5);
        $hero->setAgeRaw(250);
        $hero->setStatus(HeroStatus::Available);
        $hero->setStrRaw(50);
        $hero->setDexRaw(40);
        $hero->setKonRaw(45);
        $hero->setSpdRaw(35);
        $hero->setIntelRaw(30);
        $hero->setWilRaw(32);
        $hero->setChaRaw(28);
        $hero->setLckRaw(25);

        $record = $service->recordHero($hero, $team, GraveyardCause::Dismissed);

        $this->assertSame('Aldric', $record->getHeroName());
        $this->assertSame(Race::Human, $record->getRace());
        $this->assertSame(5, $record->getFinalLevel());
        $this->assertSame('dismissed', $record->getCauseOfDeath());
        $this->assertSame(5, $record->getFinalStats()['str']);
    }
}
