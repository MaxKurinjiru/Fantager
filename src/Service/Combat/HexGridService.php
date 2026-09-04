<?php

declare(strict_types=1);

namespace App\Service\Combat;

use App\ValueObject\Combat\HexCoordinate;

/**
 * Service providing hexagonal grid math, pathfinding, Line of Sight (LoS), and tactical calculations.
 *
 * Canonical starting positions (11×8 flat-topped axial, q=0..10, r=0..7):
 * - Team A (Home / Left): front (2,2/4/6), back (1,1/3/5)
 * - Team B (Away / Right): front (8,2/4/6), back (9,1/3/5)
 */
class HexGridService
{
    public const GRID_WIDTH = 11;
    public const GRID_HEIGHT = 8;

    /**
     * Determine weapon reach in hexes based on weapon sub-type (ItemSubType values).
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
     * Check if a coordinate is within the valid 11×8 battlefield grid.
     */
    public function isWithinGrid(HexCoordinate $coord): bool
    {
        return $coord->q >= 0 && $coord->q < self::GRID_WIDTH && $coord->r >= 0 && $coord->r < self::GRID_HEIGHT;
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
                'front_1' => ['q' => 2, 'r' => 2],
                'front_2' => ['q' => 2, 'r' => 4],
                'front_3' => ['q' => 2, 'r' => 6],
                'back_1' => ['q' => 1, 'r' => 1],
                'back_2' => ['q' => 1, 'r' => 3],
                'back_3' => ['q' => 1, 'r' => 5],
                default => ['q' => 1, 'r' => 1],
            };
        }

        return match ($slot) {
            'front_1' => ['q' => 8, 'r' => 2],
            'front_2' => ['q' => 8, 'r' => 4],
            'front_3' => ['q' => 8, 'r' => 6],
            'back_1' => ['q' => 9, 'r' => 1],
            'back_2' => ['q' => 9, 'r' => 3],
            'back_3' => ['q' => 9, 'r' => 5],
            default => ['q' => 9, 'r' => 1],
        };
    }

    /**
     * Hexagonal A* Pathfinding from start to goal, avoiding occupied or impassable hexes.
     *
     * @param list<HexCoordinate> $occupiedHexes
     *
     * @return list<HexCoordinate> Path excluding start position
     */
    public function findPath(HexCoordinate $start, HexCoordinate $goal, array $occupiedHexes = []): array
    {
        if ($start->equals($goal)) {
            return [];
        }

        if (!$this->isWithinGrid($goal)) {
            return [];
        }

        $occupiedMap = [];
        foreach ($occupiedHexes as $hex) {
            if (!$hex->equals($start) && !$hex->equals($goal)) {
                $occupiedMap[$hex->key()] = true;
            }
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
        $fScore = [$startKey => $start->distance($goal)];

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
            if ($current->equals($goal)) {
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
                if (!$this->isWithinGrid($neighbor)) {
                    continue;
                }

                $neighborKey = $neighbor->key();
                if (isset($occupiedMap[$neighborKey])) {
                    continue;
                }

                $tentativeG = ($gScore[$currentKey] ?? \PHP_INT_MAX) + 1;
                if ($tentativeG < ($gScore[$neighborKey] ?? \PHP_INT_MAX)) {
                    $cameFrom[$neighborKey] = $current;
                    $gScore[$neighborKey] = $tentativeG;
                    $fScore[$neighborKey] = $tentativeG + $neighbor->distance($goal);

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
