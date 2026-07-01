<?php

declare(strict_types=1);

namespace App\Tests\Service\Graveyard;

use App\Entity\Graveyard\GraveyardMemorial;
use App\Entity\Hero\Hero;
use App\Entity\Team\Team;
use App\Enum\HeroRole;
use App\Enum\HeroStatus;
use App\Enum\HeroTrait;
use App\Enum\MemorialCause;
use App\Enum\Race;
use App\Service\Graveyard\GraveyardService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class GraveyardServiceTest extends TestCase
{
    public function testCreateMemorialCreatesSnapshot(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);

        $ratingCalculator = $this->createMock(\App\Service\Hero\HeroRatingCalculator::class);
        $ratingCalculator->method('calculate')->willReturn(new \App\ValueObject\Hero\HeroRating(50, 1200));

        $chronicleService = $this->createMock(\App\Service\Hero\HeroChronicleService::class);
        $service = new GraveyardService($em, $ratingCalculator, $chronicleService);

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
        $hero->setTrait(HeroTrait::QuickLearner);

        $record = $service->createMemorial($hero, $team, MemorialCause::Dismissed);

        $this->assertSame('Aldric', $record->getName());
        $this->assertSame(Race::Human, $record->getRace());
        $this->assertSame(HeroRole::Combatant, $record->getRoleAtDeparture());
        $this->assertSame(5, $record->getFinalLevel());
        $this->assertSame(MemorialCause::Dismissed, $record->getCause());
        $this->assertSame(5, $record->getFinalStats()['str']);
        $this->assertSame(50, $record->getFinalStats()['base_ovr']);
        $this->assertSame(1200, $record->getFinalStats()['complex_rating']);
        $this->assertSame(HeroTrait::QuickLearner, $record->getTrait());

        $serialized = $service->serializeMemorial($record);
        $this->assertSame(HeroTrait::QuickLearner, $serialized['trait']);
    }
}
