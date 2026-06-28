<?php

declare(strict_types=1);

namespace App\Tests\Service\Graveyard;

use App\Entity\Graveyard\GraveyardMemorial;
use App\Entity\Team\Team;
use App\Enum\HeroRole;
use App\Enum\MemorialCause;
use App\Enum\Race;
use App\Repository\Graveyard\GraveyardMemorialRepository;
use App\Service\Graveyard\GraveyardPresenter;
use App\Service\Graveyard\GraveyardService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class GraveyardPresenterTest extends TestCase
{
    public function testPresentSummaryAndList(): void
    {
        $team = new Team();

        $memorial = new GraveyardMemorial();
        $memorial->setTeam($team);
        $memorial->setName('Aldric');
        $memorial->setRace(Race::Human);
        $memorial->setRoleAtDeparture(HeroRole::Combatant);
        $memorial->setCause(MemorialCause::Dismissed);
        $memorial->setAge(250);
        $memorial->setFinalLevel(5);
        $memorial->setFinalStats(['str' => 50]);
        $memorial->setDepartedAt(new \DateTimeImmutable('2026-06-01'));

        $repository = $this->createMock(GraveyardMemorialRepository::class);
        $repository->expects($this->once())->method('countByTeam')->with($team)->willReturn(1);
        $repository->expects($this->once())->method('countByCauseForTeam')->with($team)->willReturn(['dismissed' => 1]);
        $repository->expects($this->once())->method('averageAgeForTeam')->with($team)->willReturn(250.0);
        $repository->expects($this->once())->method('findByTeamFiltered')
            ->with($team, null, null, null, null)
            ->willReturn([$memorial]);

        $presenter = new GraveyardPresenter(
            $repository,
            new GraveyardService(
                $this->createMock(EntityManagerInterface::class),
                $this->createMock(\App\Service\Hero\HeroRatingCalculator::class),
            ),
        );

        $summary = $presenter->presentSummary($team);
        $list = $presenter->presentListForTeam($team);

        $this->assertSame(1, $summary['total']);
        $this->assertSame(['dismissed' => 1], $summary['by_cause']);
        $this->assertSame(250.0, $summary['average_age']);
        $this->assertCount(1, $list);
        $this->assertSame('Aldric', $list[0]['name']);
        $this->assertSame('dismissed', $list[0]['cause']);
    }
}
