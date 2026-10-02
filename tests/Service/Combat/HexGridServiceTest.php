<?php

declare(strict_types=1);

namespace App\Tests\Service\Combat;

use App\Enum\Race;
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
        $this->assertSame(['q' => 4, 'r' => 3], $this->hexGridService->getInitialHexForSlot('front_1', 'a'));
        $this->assertSame(['q' => 1, 'r' => 2], $this->hexGridService->getInitialHexForSlot('back_1', 'a'));
        $this->assertSame(['q' => 12, 'r' => 6], $this->hexGridService->getInitialHexForSlot('front_2', 'b'));
        $this->assertSame(['q' => 15, 'r' => 8], $this->hexGridService->getInitialHexForSlot('back_3', 'b'));
    }

    public function testFlowerFootprintHasSevenHexesAndFitsAtSpawn(): void
    {
        $origin = new HexCoordinate(4, 5);
        $footprint = $this->hexGridService->getFootprint($origin, 1);

        $this->assertCount(7, $footprint);
        $this->assertTrue($this->hexGridService->footprintFits($origin, 1));
        $this->assertFalse($this->hexGridService->footprintFits(new HexCoordinate(0, 0), 1));
    }

    public function testEngagementDistanceAccountsForFlowerRadius(): void
    {
        $giant = new HexCoordinate(4, 2);
        $human = new HexCoordinate(6, 2);

        $this->assertSame(2, $giant->distance($human));
        $this->assertSame(1, $this->hexGridService->engagementDistance($giant, 1, $human, 0));
        $this->assertSame(0, $this->hexGridService->engagementDistance($giant, 1, $human, 1));
    }

    public function testStartingLanesKeepFlowerCentresThreeApart(): void
    {
        $slots = ['front_1', 'front_2', 'front_3', 'back_1', 'back_2', 'back_3'];
        $origins = [];
        foreach ($slots as $slot) {
            $pos = $this->hexGridService->getInitialHexForSlot($slot, 'a');
            $origins[$slot] = new HexCoordinate($pos['q'], $pos['r']);
            $this->assertTrue($this->hexGridService->footprintFits($origins[$slot], 1), $slot.' flower must fit');
        }

        $names = array_keys($origins);
        for ($i = 0; $i < count($names); ++$i) {
            for ($j = $i + 1; $j < count($names); ++$j) {
                $dist = $origins[$names[$i]]->distance($origins[$names[$j]]);
                $this->assertGreaterThanOrEqual(3, $dist, $names[$i].' vs '.$names[$j]);
            }
        }
    }

    public function testFindPathStopsAtMeleeReachAgainstFlower(): void
    {
        $human = new HexCoordinate(8, 2);
        $giant = new HexCoordinate(12, 2);
        $flower = $this->hexGridService->getFootprint($giant, 1);

        $path = $this->hexGridService->findPath($human, $giant, $flower, 0, 1, 1);
        $this->assertNotEmpty($path);
        $stand = $path[array_key_last($path)];
        $this->assertSame(1, $this->hexGridService->engagementDistance($stand, 0, $giant, 1));
        foreach ($flower as $hex) {
            $this->assertFalse($stand->equals($hex), 'melee close must not step onto the flower');
        }
    }

    public function testEntAndGiantUseFlowerRadius(): void
    {
        $this->assertSame(1, Race::Ent->hexFootprintRadius());
        $this->assertSame(1, Race::Giant->hexFootprintRadius());
        $this->assertSame(0, Race::Human->hexFootprintRadius());
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
