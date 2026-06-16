<?php

declare(strict_types=1);

namespace App\Tests\Service\Graveyard;

use App\Entity\Graveyard\GraveyardMemorial;
use App\Entity\Hero\Hero;
use App\Entity\Team\Team;
use App\Enum\HeroRole;
use App\Enum\HeroStatus;
use App\Enum\MemorialCause;
use App\Enum\Race;
use App\Service\Graveyard\GraveyardService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class GraveyardServiceTest extends TestCase
{
    public function testRecordMemorialCreatesSnapshot(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(GraveyardMemorial::class));

        $service = new GraveyardService($em);

        $team = new Team();
        $hero = new Hero();
        $hero->setName('Aldric');
        $hero->setRace(Race::Human);
        $hero->setRole(HeroRole::Combatant);
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

        $record = $service->recordMemorial($hero, $team, MemorialCause::Dismissed);

        $this->assertSame('Aldric', $record->getName());
        $this->assertSame(Race::Human, $record->getRace());
        $this->assertSame(HeroRole::Combatant, $record->getRoleAtDeparture());
        $this->assertSame(5, $record->getFinalLevel());
        $this->assertSame(MemorialCause::Dismissed, $record->getCause());
        $this->assertSame(5, $record->getFinalStats()['str']);
    }
}
