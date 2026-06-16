<?php

declare(strict_types=1);

namespace App\Tests\Service\Formation;

use App\Entity\Formation\Formation;
use App\Entity\Formation\FormationSlot;
use App\Entity\League\LeagueFixture;
use App\Entity\Team\Team;
use App\Enum\FormationApproach;
use App\Enum\FormationPosition;
use App\Enum\LeagueFixtureStatus;
use App\Repository\Formation\FormationRepository;
use App\Repository\League\LeagueFixtureRepository;
use App\Service\Formation\FixtureFormationService;
use App\Service\Formation\FormationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class FixtureFormationServiceTest extends TestCase
{
    public function testResolveFormationUsesDefaultWhenAssignmentIsNull(): void
    {
        $homeTeam = $this->createTeam(1);
        $awayTeam = $this->createTeam(2);
        $defaultFormation = $this->createSavedFormation($homeTeam, true);

        $fixture = $this->createFixture($homeTeam, $awayTeam);
        $fixture->setHomeFormation(null);

        $service = $this->createService($defaultFormation);

        $resolved = $service->resolveFormation($fixture, $homeTeam);

        $this->assertSame($defaultFormation, $resolved);
    }

    public function testResolveFormationUsesAssignedSavedFormation(): void
    {
        $homeTeam = $this->createTeam(1);
        $awayTeam = $this->createTeam(2);
        $defaultFormation = $this->createSavedFormation($homeTeam, true);
        $savedFormation = $this->createSavedFormation($homeTeam, false);

        $fixture = $this->createFixture($homeTeam, $awayTeam);
        $fixture->setHomeFormation($savedFormation);

        $service = $this->createService($defaultFormation);

        $this->assertSame($savedFormation, $service->resolveFormation($fixture, $homeTeam));
    }

    public function testAssignDefaultClearsTemporaryFormation(): void
    {
        $homeTeam = $this->createTeam(1);
        $awayTeam = $this->createTeam(2);
        $defaultFormation = $this->createSavedFormation($homeTeam, true);
        $tempFormation = $this->createTemporaryFormation($homeTeam);

        $fixture = $this->createFixture($homeTeam, $awayTeam);
        $fixture->setHomeFormation($tempFormation);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $formationService = $this->createMock(FormationService::class);
        $formationService->method('findDefaultForTeam')->willReturn($defaultFormation);
        $formationService->expects($this->once())->method('deleteTemporary')->with($tempFormation);

        $service = new FixtureFormationService(
            $formationService,
            $this->createMock(FormationRepository::class),
            $this->createMock(LeagueFixtureRepository::class),
            $em,
        );
        $service->assignDefault($fixture, $homeTeam);

        $this->assertNull($fixture->getHomeFormation());
    }

    public function testCleanupTemporaryFormationsAfterCompletion(): void
    {
        $homeTeam = $this->createTeam(1);
        $awayTeam = $this->createTeam(2);
        $homeTemp = $this->createTemporaryFormation($homeTeam);
        $awayTemp = $this->createTemporaryFormation($awayTeam);

        $fixture = $this->createFixture($homeTeam, $awayTeam);
        $fixture->setHomeFormation($homeTemp);
        $fixture->setAwayFormation($awayTemp);

        $formationService = $this->createMock(FormationService::class);
        $formationService->expects($this->exactly(2))
            ->method('deleteTemporary');

        $formationRepository = $this->createMock(FormationRepository::class);
        $formationRepository->method('findTemporaryByFixture')->willReturn([]);

        $service = new FixtureFormationService(
            $formationService,
            $formationRepository,
            $this->createMock(LeagueFixtureRepository::class),
            $this->createMock(EntityManagerInterface::class),
        );

        $service->cleanupTemporaryFormationsAfterCompletion($fixture);

        $this->assertNull($fixture->getHomeFormation());
        $this->assertNull($fixture->getAwayFormation());
    }

    public function testCleanupStaleTemporaryFormationsForKingdom(): void
    {
        $kingdom = new \App\Entity\Kingdom\Kingdom();
        $homeTeam = $this->createTeam(1);
        $awayTeam = $this->createTeam(2);
        $temp = $this->createTemporaryFormation($homeTeam);

        $fixture = $this->createFixture($homeTeam, $awayTeam);
        $fixture->setStatus(LeagueFixtureStatus::Completed);
        $fixture->setHomeFormation($temp);

        $fixtureRepository = $this->createMock(LeagueFixtureRepository::class);
        $fixtureRepository->method('findCompletedWithTemporaryAssignments')->willReturn([$fixture]);

        $formationRepository = $this->createMock(FormationRepository::class);
        $formationRepository->method('findTemporaryByFixture')->willReturn([]);
        $formationRepository->method('findTemporaryWithCompletedSourceFixture')->willReturn([]);

        $formationService = $this->createMock(FormationService::class);
        $formationService->expects($this->once())->method('deleteTemporary')->with($temp);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $service = new FixtureFormationService(
            $formationService,
            $formationRepository,
            $fixtureRepository,
            $em,
        );

        $removed = $service->cleanupStaleTemporaryFormationsForKingdom($kingdom);

        $this->assertSame(1, $removed);
        $this->assertNull($fixture->getHomeFormation());
    }

    public function testGetAssignmentStateReportsDefaultMode(): void
    {
        $homeTeam = $this->createTeam(1);
        $awayTeam = $this->createTeam(2);
        $defaultFormation = $this->createSavedFormation($homeTeam, true);
        $fixture = $this->createFixture($homeTeam, $awayTeam);

        $formationService = $this->createMock(FormationService::class);
        $formationService->method('findDefaultForTeam')->willReturn($defaultFormation);
        $formationService->method('requireDefaultForTeam')->willReturn($defaultFormation);
        $formationService->method('listByTeam')->willReturn([$defaultFormation]);
        $formationService->method('serialize')->willReturnCallback(
            static fn (Formation $formation): array => [
                'id' => $formation->getId(),
                'name' => $formation->getName(),
                'approach' => $formation->getApproach()->value,
                'is_default' => $formation->isDefault(),
                'is_temporary' => $formation->isTemporary(),
                'source_fixture_id' => null,
                'slots' => [],
            ],
        );

        $formationRepository = $this->createMock(FormationRepository::class);
        $formationRepository->method('countSavedByTeam')->willReturn(1);

        $service = new FixtureFormationService(
            $formationService,
            $formationRepository,
            $this->createMock(LeagueFixtureRepository::class),
            $this->createMock(EntityManagerInterface::class),
        );

        $state = $service->getAssignmentState($fixture, $homeTeam);

        $this->assertSame('default', $state['assignment']['mode']);
        $this->assertNull($state['assignment']['formation_id']);
        $this->assertSame($defaultFormation->getId(), $state['effective_formation']['id']);
        $this->assertSame(FormationService::MAX_SAVED_FORMATIONS, $state['saved_limit']);
    }

    private function createService(?Formation $defaultFormation): FixtureFormationService
    {
        $formationService = $this->createMock(FormationService::class);
        $formationService->method('findDefaultForTeam')->willReturn($defaultFormation);
        $formationService->method('requireDefaultForTeam')->willReturn($defaultFormation);

        return new FixtureFormationService(
            $formationService,
            $this->createMock(FormationRepository::class),
            $this->createMock(LeagueFixtureRepository::class),
            $this->createMock(EntityManagerInterface::class),
        );
    }

    private function createTeam(int $id): Team
    {
        $team = new Team();
        $reflection = new \ReflectionProperty(Team::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($team, $id);

        return $team;
    }

    private function createFixture(Team $homeTeam, Team $awayTeam): LeagueFixture
    {
        $fixture = new LeagueFixture();
        $fixture->setHomeTeam($homeTeam);
        $fixture->setAwayTeam($awayTeam);
        $fixture->setScheduledAt(new \DateTimeImmutable('2026-06-20 18:00:00'));
        $fixture->setStatus(LeagueFixtureStatus::Scheduled);

        $reflection = new \ReflectionProperty(LeagueFixture::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($fixture, 100);

        return $fixture;
    }

    private function createSavedFormation(Team $team, bool $isDefault): Formation
    {
        static $nextId = 10;

        $formation = new Formation();
        $formation->setTeam($team);
        $formation->setName($isDefault ? 'Default' : 'Alt');
        $formation->setApproach(FormationApproach::Balanced);
        $formation->setIsDefault($isDefault);
        $formation->setIsTemporary(false);

        $reflection = new \ReflectionProperty(Formation::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($formation, $nextId++);

        $slot = new FormationSlot();
        $slot->setFormation($formation);
        $slot->setPosition(FormationPosition::Front1);
        $formation->addSlot($slot);

        return $formation;
    }

    private function createTemporaryFormation(Team $team): Formation
    {
        $formation = $this->createSavedFormation($team, false);
        $formation->setIsTemporary(true);
        $formation->setName('Match Custom');

        return $formation;
    }
}
