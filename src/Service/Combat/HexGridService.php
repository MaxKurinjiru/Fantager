<?php

declare(strict_types=1);

namespace App\Service\Combat;

use App\ValueObject\Combat\HexCoordinate;

/**
 * Service providing hexagonal grid math, pathfinding, Line of Sight (LoS), and tactical calculations.
 *
 * Canonical starting positions (17×11 flat-topped axial, q=0..16, r=0..10).
 * Lane centres are at least 3 hexes apart so 7-hex flowers (Ent / Giant) do not overlap.
 * Front row is staggered +1 r vs back so a lane's backliner does not share LoS with its frontliner:
 * - Team A (Home / Left): front (4, 3/6/9), back (1, 2/5/8)
 * - Team B (Away / Right): front (12, 3/6/9), back (15, 2/5/8)
 */
class HexGridService
{
    public const GRID_WIDTH = 17;
    public const GRID_HEIGHT = 11;

    /**
     * Determine weapon reach in hexes based on weapon sub-type (ItemSubType values).
     * Reach is measured as engagement distance (edge-to-edge between footprints).
     */
    public function getWeaponReach(?string $weaponSubType): int
    {
        return match ($weaponSubType) {
            'spear', 'polearm' => 2,
            'bow', 'crossbow', 'staff', 'wand' => 5,
            default => 1,
        };
    }

    public function isRangedWeapon(?string $weaponSubType): bool
    {
        return in_array($weaponSubType, ['bow', 'crossbow', 'staff', 'wand'], true);
    }

    /**
     * Check if a coordinate is within the valid 17×11 battlefield grid.
     */
    public function isWithinGrid(HexCoordinate $coord): bool
    {
        return $coord->q >= 0 && $coord->q < self::GRID_WIDTH && $coord->r >= 0 && $coord->r < self::GRID_HEIGHT;
    }

    /**
     * Hexes occupied by a unit centred on $origin. Radius 0 = single hex; radius 1 = 7-hex flower.
     *
     * @return list<HexCoordinate>
     */
    public function getFootprint(HexCoordinate $origin, int $radius = 0): array
    {
        if ($radius <= 0) {
            return [$origin];
        }

        $hexes = [$origin];
        foreach ($origin->getNeighbors() as $neighbor) {
            $hexes[] = $neighbor;
        }

        return $hexes;
    }

    /**
     * True when every hex of the footprint lies on the battlefield.
     */
    public function footprintFits(HexCoordinate $origin, int $radius = 0): bool
    {
        foreach ($this->getFootprint($origin, $radius) as $hex) {
            if (!$this->isWithinGrid($hex)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Edge-to-edge distance between two footprints (0 = overlapping or concentric).
     */
    public function engagementDistance(
        HexCoordinate $originA,
        int $radiusA,
        HexCoordinate $originB,
        int $radiusB,
    ): int {
        return max(0, $originA->distance($originB) - max(0, $radiusA) - max(0, $radiusB));
    }

    /**
     * Canonical slot → hex mapping shared by simulation and docs.
     *
     * @return array{q: int, r: int}
     */
    public function getInitialHexForSlot(string $slot, string $sideKey): array
    {
        if ('a' === $sideKey) {
            return match ($slot) {
                'front_1' => ['q' => 4, 'r' => 3],
                'front_2' => ['q' => 4, 'r' => 6],
                'front_3' => ['q' => 4, 'r' => 9],
                'back_1' => ['q' => 1, 'r' => 2],
                'back_2' => ['q' => 1, 'r' => 5],
                'back_3' => ['q' => 1, 'r' => 8],
                default => ['q' => 1, 'r' => 2],
            };
        }

        return match ($slot) {
            'front_1' => ['q' => 12, 'r' => 3],
            'front_2' => ['q' => 12, 'r' => 6],
            'front_3' => ['q' => 12, 'r' => 9],
            'back_1' => ['q' => 15, 'r' => 2],
            'back_2' => ['q' => 15, 'r' => 5],
            'back_3' => ['q' => 15, 'r' => 8],
            default => ['q' => 15, 'r' => 2],
        };
    }

    /**
     * Hexagonal A* Pathfinding from start toward goal, avoiding occupied or impassable hexes.
     *
     * $moverRadius / $goalRadius describe hex-ball footprints. When $stopAtDistance > 0 the search
     * ends at the first centre whose engagement distance to the goal is within that threshold
     * (used to close to weapon reach without walking onto the target body).
     *
     * @param list<HexCoordinate> $occupiedHexes
     *
     * @return list<HexCoordinate> Path excluding start position
     */
    public function findPath(
        HexCoordinate $start,
        HexCoordinate $goal,
        array $occupiedHexes = [],
        int $moverRadius = 0,
        int $goalRadius = 0,
        int $stopAtDistance = 0,
    ): array {
        if ($this->engagementDistance($start, $moverRadius, $goal, $goalRadius) <= $stopAtDistance) {
            return [];
        }

        if (0 === $stopAtDistance && !$this->isWithinGrid($goal)) {
            return [];
        }

        if (!$this->footprintFits($start, $moverRadius)) {
            return [];
        }

        $occupiedMap = [];
        foreach ($occupiedHexes as $hex) {
            if ($hex->equals($start)) {
                continue;
            }
            if (0 === $stopAtDistance && $hex->equals($goal)) {
                continue;
            }
            $occupiedMap[$hex->key()] = true;
        }

        /** @var array<string, HexCoordinate> $openSet */
        $openSet = [];
        $startKey = $start->key();
        $openSet[$startKey] = $start;

        /** @var array<string, HexCoordinate> $cameFrom */
        $cameFrom = [];

        /** @var array<string, int> $gScore */
        $gScore = [$startKey => 0];

        /** @var array<string, int> $fScore */
        $fScore = [$startKey => $this->pathHeuristic($start, $goal, $moverRadius, $goalRadius, $stopAtDistance)];

        while (!empty($openSet)) {
            $currentKey = null;
            $lowestF = \PHP_INT_MAX;

            foreach ($openSet as $key => $node) {
                $score = $fScore[$key] ?? \PHP_INT_MAX;
                if ($score < $lowestF) {
                    $lowestF = $score;
                    $currentKey = $key;
                }
            }

            if (null === $currentKey) {
                break;
            }

            $current = $openSet[$currentKey];
            if ($this->engagementDistance($current, $moverRadius, $goal, $goalRadius) <= $stopAtDistance) {
                $path = [];
                $curr = $current;
                $currKey = $currentKey;
                while (isset($cameFrom[$currKey])) {
                    $path[] = $curr;
                    $curr = $cameFrom[$currKey];
                    $currKey = $curr->key();
                }

                return array_reverse($path);
            }

            unset($openSet[$currentKey]);

            foreach ($current->getNeighbors() as $neighbor) {
                if (!$this->footprintFits($neighbor, $moverRadius)) {
                    continue;
                }

                if ($this->footprintHitsOccupied($neighbor, $moverRadius, $occupiedMap, $start, $goal, $stopAtDistance)) {
                    continue;
                }

                $neighborKey = $neighbor->key();
                $tentativeG = ($gScore[$currentKey] ?? \PHP_INT_MAX) + 1;
                if ($tentativeG < ($gScore[$neighborKey] ?? \PHP_INT_MAX)) {
                    $cameFrom[$neighborKey] = $current;
                    $gScore[$neighborKey] = $tentativeG;
                    $fScore[$neighborKey] = $tentativeG + $this->pathHeuristic($neighbor, $goal, $moverRadius, $goalRadius, $stopAtDistance);

                    if (!isset($openSet[$neighborKey])) {
                        $openSet[$neighborKey] = $neighbor;
                    }
                }
            }
        }

        return [];
    }

    /**
     * Clear Line of Sight using cube-rounded hex interpolation (axial Bresenham equivalent).
     *
     * @param list<HexCoordinate> $blockingHexes
     */
    public function hasLineOfSight(HexCoordinate $start, HexCoordinate $target, array $blockingHexes = []): bool
    {
        $dist = $start->distance($target);
        if ($dist <= 1) {
            return true;
        }

        $blockingMap = [];
        foreach ($blockingHexes as $hex) {
            if (!$hex->equals($start) && !$hex->equals($target)) {
                $blockingMap[$hex->key()] = true;
            }
        }

        foreach ($this->hexLine($start, $target) as $i => $hex) {
            if (0 === $i || $hex->equals($target)) {
                continue;
            }
            if (isset($blockingMap[$hex->key()])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<HexCoordinate>
     */
    public function hexLine(HexCoordinate $start, HexCoordinate $target): array
    {
        $dist = $start->distance($target);
        if (0 === $dist) {
            return [$start];
        }

        $line = [];
        for ($i = 0; $i <= $dist; ++$i) {
            $t = $i / $dist;
            $line[] = $this->hexLerpRound($start, $target, $t);
        }

        return $line;
    }

    /**
     * Calculate AP cost and fatigue generated by moving a number of hexes.
     * Heavy armor adds +2 Fatigue per hex moved.
     *
     * @return array{apCost: int, fatigueGenerated: int}
     */
    public function calculateMovementCost(int $hexesMoved, ?string $armorType = null): array
    {
        $apCost = $hexesMoved * 2;
        $fatiguePerHex = in_array($armorType, ['heavy_armor', 'heavy', 'plate'], true) ? 2 : 0;

        return [
            'apCost' => $apCost,
            'fatigueGenerated' => $hexesMoved * $fatiguePerHex,
        ];
    }

    /**
     * Action points available this turn. Uses baseInitiative as Effective_SPD proxy.
     */
    public function calculateActionPoints(int $effectiveSpd): int
    {
        return 4 + intdiv(max(0, $effectiveSpd), 5);
    }

    /**
     * @param array<string, true> $occupiedMap
     */
    private function footprintHitsOccupied(
        HexCoordinate $origin,
        int $radius,
        array $occupiedMap,
        HexCoordinate $start,
        HexCoordinate $goal,
        int $stopAtDistance,
    ): bool {
        foreach ($this->getFootprint($origin, $radius) as $hex) {
            if (!isset($occupiedMap[$hex->key()])) {
                continue;
            }
            if (0 === $stopAtDistance && $hex->equals($goal)) {
                continue;
            }
            if ($hex->equals($start)) {
                continue;
            }

            return true;
        }

        return false;
    }

    private function pathHeuristic(
        HexCoordinate $from,
        HexCoordinate $goal,
        int $moverRadius,
        int $goalRadius,
        int $stopAtDistance,
    ): int {
        return max(0, $this->engagementDistance($from, $moverRadius, $goal, $goalRadius) - $stopAtDistance);
    }

    private function hexLerpRound(HexCoordinate $a, HexCoordinate $b, float $t): HexCoordinate
    {
        $aq = (float) $a->q;
        $ar = (float) $a->r;
        $bq = (float) $b->q;
        $br = (float) $b->r;

        $q = $aq + ($bq - $aq) * $t;
        $r = $ar + ($br - $ar) * $t;
        $s = (-$aq - $ar) + ((-$bq - $br) - (-$aq - $ar)) * $t;

        return $this->cubeRound($q, $r, $s);
    }

    private function cubeRound(float $q, float $r, float $s): HexCoordinate
    {
        $rq = (int) round($q);
        $rr = (int) round($r);
        $rs = (int) round($s);

        $qDiff = abs($rq - $q);
        $rDiff = abs($rr - $r);
        $sDiff = abs($rs - $s);

        if ($qDiff > $rDiff && $qDiff > $sDiff) {
            $rq = -$rr - $rs;
        } elseif ($rDiff > $sDiff) {
            $rr = -$rq - $rs;
        }

        return new HexCoordinate($rq, $rr);
    }
}
