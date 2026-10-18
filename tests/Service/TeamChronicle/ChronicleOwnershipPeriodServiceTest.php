<?php

declare(strict_types=1);

namespace App\Tests\Service\TeamChronicle;

use App\Entity\Team\TeamChronicle;
use App\Enum\ChronicleEventType;
use App\Service\TeamChronicle\ChronicleOwnershipPeriodService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class ChronicleOwnershipPeriodServiceTest extends TestCase
{
    public function testSplitsNpcAndManagerStretchesOnJoinAndRelease(): void
    {
        $established = $this->marker(1, ChronicleEventType::TeamEstablished, '2026-01-01 00:00:00');
        $joined = $this->marker(2, ChronicleEventType::PlayerJoined, '2026-02-01 00:00:00', 'Ada', 7);
        $released = $this->marker(3, ChronicleEventType::PlayerReleased, '2026-03-01 00:00:00', 'Ada', 7);
        $rejoined = $this->marker(4, ChronicleEventType::PlayerJoined, '2026-04-01 00:00:00', 'Bea', 8);

        $periods = (new ChronicleOwnershipPeriodService($this->createStub(\App\Repository\Team\TeamChronicleRepository::class)))
            ->build([$established, $joined, $released, $rejoined]);

        $this->assertCount(4, $periods);

        $this->assertSame('e-1', $periods[0]['key']);
        $this->assertSame('npc', $periods[0]['kind']);
        $this->assertTrue($periods[0]['from_inclusive']);
        $this->assertFalse($periods[0]['to_inclusive']);
        $this->assertEquals(new \DateTimeImmutable('2026-02-01 00:00:00'), $periods[0]['to']);

        $this->assertSame('2', $periods[1]['key']);
        $this->assertSame('player', $periods[1]['kind']);
        $this->assertSame('Ada', $periods[1]['name']);
        $this->assertSame(7, $periods[1]['user_id']);
        $this->assertTrue($periods[1]['from_inclusive']);
        $this->assertTrue($periods[1]['to_inclusive']);
        $this->assertEquals(new \DateTimeImmutable('2026-03-01 00:00:00'), $periods[1]['to']);

        $this->assertSame('n-3', $periods[2]['key']);
        $this->assertSame('npc', $periods[2]['kind']);
        $this->assertFalse($periods[2]['from_inclusive']);
        $this->assertFalse($periods[2]['to_inclusive']);

        $this->assertSame('4', $periods[3]['key']);
        $this->assertSame('Bea', $periods[3]['name']);
        $this->assertNull($periods[3]['to']);
        $this->assertTrue($periods[3]['current']);
        $this->assertFalse($periods[1]['current']);
    }

    public function testUnknownPeriodKeyDoesNotFilter(): void
    {
        $service = new ChronicleOwnershipPeriodService($this->createStub(\App\Repository\Team\TeamChronicleRepository::class));
        $periods = $service->build([
            $this->marker(4, ChronicleEventType::PlayerJoined, '2026-04-01 00:00:00', 'Bea', 8),
        ]);

        $this->assertNull($service->windowFor($periods, ''));
        $this->assertNull($service->windowFor($periods, 'missing'));

        $window = $service->windowFor($periods, '4');
        $this->assertNotNull($window);
        $this->assertTrue($window->fromInclusive);
        $this->assertNull($window->to);
    }

    private function marker(
        int $id,
        ChronicleEventType $type,
        string $at,
        ?string $player = null,
        ?int $userId = null,
    ): TeamChronicle {
        $entry = new TeamChronicle();
        $entry->setType($type);
        $entry->setSubjectKey('activity.'.$type->value);
        $entry->setCreatedAt(new \DateTimeImmutable($at));
        if (null !== $player) {
            $entry->setSubjectParams(['player' => $player]);
        }
        if (null !== $userId) {
            $entry->setData(['user_id' => $userId]);
        }

        $ref = new \ReflectionProperty($entry, 'id');
        $ref->setValue($entry, $id);

        return $entry;
    }
}
