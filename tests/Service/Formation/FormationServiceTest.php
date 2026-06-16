<?php

declare(strict_types=1);

namespace App\Tests\Service\Formation;

use App\Entity\Formation\Formation;
use App\Entity\Team\Team;
use App\Repository\Formation\FormationRepository;
use App\Repository\Formation\FormationSlotRepository;
use App\Repository\Hero\HeroRepository;
use App\Repository\League\LeagueFixtureRepository;
use App\Service\Formation\FormationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class FormationServiceTest extends TestCase
{
    public function testAssertCanCreateSavedFormationAllowsUpToLimit(): void
    {
        $team = new Team();
        $repository = $this->createMock(FormationRepository::class);
        $repository->method('countSavedByTeam')->willReturn(FormationService::MAX_SAVED_FORMATIONS);

        $service = new FormationService(
            $repository,
            $this->createMock(FormationSlotRepository::class),
            $this->createMock(HeroRepository::class),
            $this->createMock(LeagueFixtureRepository::class),
            $this->createMock(EntityManagerInterface::class),
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Maximum of 4 saved formations allowed.');

        $service->assertCanCreateSavedFormation($team);
    }

    public function testSaveRejectsNewFormationWhenLimitReached(): void
    {
        $team = new Team();
        $repository = $this->createMock(FormationRepository::class);
        $repository->method('countSavedByTeam')->willReturn(FormationService::MAX_SAVED_FORMATIONS);

        $service = new FormationService(
            $repository,
            $this->createMock(FormationSlotRepository::class),
            $this->createMock(HeroRepository::class),
            $this->createMock(LeagueFixtureRepository::class),
            $this->createMock(EntityManagerInterface::class),
        );

        $this->expectException(\DomainException::class);

        $service->save($team, null, 'New Formation', \App\Enum\FormationApproach::Balanced, [], false);
    }

    public function testPromoteTemporaryRejectsWhenLimitReached(): void
    {
        $team = new Team();
        $formation = new Formation();
        $formation->setTeam($team);
        $formation->setName('Temp');
        $formation->setApproach(\App\Enum\FormationApproach::Balanced);
        $formation->setIsTemporary(true);

        $repository = $this->createMock(FormationRepository::class);
        $repository->method('countSavedByTeam')->willReturn(FormationService::MAX_SAVED_FORMATIONS);

        $service = new FormationService(
            $repository,
            $this->createMock(FormationSlotRepository::class),
            $this->createMock(HeroRepository::class),
            $this->createMock(LeagueFixtureRepository::class),
            $this->createMock(EntityManagerInterface::class),
        );

        $this->expectException(\DomainException::class);

        $service->promoteTemporary($formation, $team, 'Saved', false);
    }
}
