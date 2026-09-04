<?php

declare(strict_types=1);

namespace App\Tests\Service\Combat;

use App\Service\Combat\HexGridService;
use App\ValueObject\Combat\HexCoordinate;
use PHPUnit\Framework\TestCase;

class HexGridServiceTest extends TestCase
{
    private HexGridService $hexGridService;

    protected function setUp(): void
    {
        $this->hexGridService = new HexGridService();
    }

    public function testDistanceCalculation(): void
    {
        $start = new HexCoordinate(1, 2);
        $target = new HexCoordinate(4, 2);

        $this->assertSame(3, $start->distance($target));
    }

    public function testWeaponReach(): void
    {
        $this->assertSame(1, $this->hexGridService->getWeaponReach('one_handed_sword'));
        $this->assertSame(1, $this->hexGridService->getWeaponReach('one_handed_axe'));
        $this->assertSame(2, $this->hexGridService->getWeaponReach('spear'));
        $this->assertSame(2, $this->hexGridService->getWeaponReach('polearm'));
        $this->assertSame(5, $this->hexGridService->getWeaponReach('bow'));
        $this->assertSame(5, $this->hexGridService->getWeaponReach('crossbow'));
        $this->assertSame(5, $this->hexGridService->getWeaponReach('wand'));
    }

    public function testInitialHexPositionsAreCanonical(): void
    {
        $this->assertSame(['q' => 2, 'r' => 2], $this->hexGridService->getInitialHexForSlot('front_1', 'a'));
        $this->assertSame(['q' => 1, 'r' => 1], $this->hexGridService->getInitialHexForSlot('back_1', 'a'));
        $this->assertSame(['q' => 8, 'r' => 4], $this->hexGridService->getInitialHexForSlot('front_2', 'b'));
        $this->assertSame(['q' => 9, 'r' => 5], $this->hexGridService->getInitialHexForSlot('back_3', 'b'));
    }

    public function testFindPath(): void
    {
        $start = new HexCoordinate(0, 0);
        $goal = new HexCoordinate(3, 0);

        $path = $this->hexGridService->findPath($start, $goal, []);
        $this->assertCount(3, $path);
        $this->assertTrue(end($path)->equals($goal));
    }

    public function testFindPathAvoidsOccupiedHexes(): void
    {
        $start = new HexCoordinate(0, 0);
        $goal = new HexCoordinate(2, 0);
        $blocker = new HexCoordinate(1, 0);

        $path = $this->hexGridService->findPath($start, $goal, [$blocker]);
        $this->assertNotEmpty($path);
        foreach ($path as $hex) {
            $this->assertFalse($hex->equals($blocker));
        }
        $this->assertTrue(end($path)->equals($goal));
    }

    public function testLineOfSight(): void
    {
        $start = new HexCoordinate(0, 0);
        $target = new HexCoordinate(4, 0);

        $this->assertTrue($this->hexGridService->hasLineOfSight($start, $target, []));

        $blocker = new HexCoordinate(2, 0);
        $this->assertFalse($this->hexGridService->hasLineOfSight($start, $target, [$blocker]));
    }

    public function testMovementCost(): void
    {
        $costLight = $this->hexGridService->calculateMovementCost(3, 'light_armor');
        $this->assertSame(6, $costLight['apCost']);
        $this->assertSame(0, $costLight['fatigueGenerated']);

        $costHeavy = $this->hexGridService->calculateMovementCost(3, 'heavy_armor');
        $this->assertSame(6, $costHeavy['apCost']);
        $this->assertSame(6, $costHeavy['fatigueGenerated']);
    }

    public function testActionPoints(): void
    {
        $this->assertSame(4, $this->hexGridService->calculateActionPoints(0));
        $this->assertSame(6, $this->hexGridService->calculateActionPoints(10));
    }

    public function testRearFlankCalculation(): void
    {
        $target = new HexCoordinate(4, 4);
        $attackerFront = new HexCoordinate(6, 4);
        $attackerRear = new HexCoordinate(2, 4);

        $this->assertFalse($attackerFront->isRearArc($target, 1));
        $this->assertTrue($attackerRear->isRearArc($target, 1));
    }
}
