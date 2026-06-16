<?php

declare(strict_types=1);

namespace App\Tests\Service\Team;

use App\Entity\Hero\Hero;
use App\Entity\Team\Team;
use App\Enum\HeroRole;
use App\Enum\HeroStatus;
use App\Repository\Hero\HeroRepository;
use App\Service\Team\TeamRosterService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class TeamRosterServiceTest extends TestCase
{
    public function testCanRemoveWhenAboveMinimum(): void
    {
        $team = new Team();
        $hero = new Hero();
        $hero->setRole(HeroRole::Combatant);
        $hero->setStatus(HeroStatus::Available);

        $repo = $this->createMock(HeroRepository::class);
        $repo->method('countCombatReadyByTeam')->with($team)->willReturn(7);

        $service = new TeamRosterService($repo);

        $this->assertTrue($service->canRemoveCombatReadyHero($team, $hero));
    }

    public function testCannotRemoveWhenAtMinimum(): void
    {
        $team = new Team();
        $hero = new Hero();
        $hero->setRole(HeroRole::Combatant);
        $hero->setStatus(HeroStatus::Available);

        $repo = $this->createMock(HeroRepository::class);
        $repo->method('countCombatReadyByTeam')->with($team)->willReturn(6);

        $service = new TeamRosterService($repo);

        $this->assertFalse($service->canRemoveCombatReadyHero($team, $hero));
    }

    public function testIsCombatReadyWhenAssignedToTrainer(): void
    {
        $team = new Team();
        $trainer = new Hero();
        $trainer->setRole(HeroRole::Trainer);

        $hero = new Hero();
        $hero->setRole(HeroRole::Combatant);
        $hero->setStatus(HeroStatus::Available);
        $hero->setTrainer($trainer);

        $repo = $this->createMock(HeroRepository::class);
        $service = new TeamRosterService($repo);

        $this->assertTrue($service->isCombatReady($hero));
    }

    public function testIsNotCombatReadyWhenRecovering(): void
    {
        $hero = new Hero();
        $hero->setRole(HeroRole::Combatant);
        $hero->setStatus(HeroStatus::Recovering);

        $repo = $this->createMock(HeroRepository::class);
        $service = new TeamRosterService($repo);

        $this->assertFalse($service->isCombatReady($hero));
    }

    public function testAssertThrowsWhenAtMinimum(): void
    {
        $team = new Team();
        $hero = new Hero();
        $hero->setRole(HeroRole::Combatant);
        $hero->setStatus(HeroStatus::Available);

        $repo = $this->createMock(HeroRepository::class);
        $repo->method('countCombatReadyByTeam')->with($team)->willReturn(6);

        $service = new TeamRosterService($repo);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot remove hero. Team must keep at least 6 combat-ready heroes');

        $service->assertCanRemoveCombatReadyHero($team, $hero);
    }
}
