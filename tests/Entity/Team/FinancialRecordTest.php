<?php

declare(strict_types=1);

namespace App\Tests\Entity\Team;

use App\Entity\Team\FinancialRecord;
use App\Entity\Team\Team;
use App\Enum\FinancialRecordActor;
use App\Enum\FinancialRecordType;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class FinancialRecordTest extends TestCase
{
    public function testFinancialRecordProperties(): void
    {
        $team = $this->createMock(Team::class);
        $record = new FinancialRecord();

        $record->setTeam($team);
        $record->setType(FinancialRecordType::LeagueReward);
        $record->setActor(FinancialRecordActor::System);
        $record->setGoldChange(500);

        $context = ['battle_id' => 42];
        $record->setContext($context);

        $createdAt = new \DateTimeImmutable('2026-06-04 12:00:00');
        $record->setCreatedAt($createdAt);

        $this->assertSame($team, $record->getTeam());
        $this->assertSame(FinancialRecordType::LeagueReward, $record->getType());
        $this->assertSame(FinancialRecordActor::System, $record->getActor());
        $this->assertSame(500, $record->getGoldChange());
        $this->assertSame($context, $record->getContext());
        $this->assertSame($createdAt, $record->getCreatedAt());
    }

    public function testDefaultValues(): void
    {
        $record = new FinancialRecord();
        $this->assertSame(0, $record->getGoldChange());
        $this->assertSame([], $record->getContext());
    }
}
