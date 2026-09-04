<?php

declare(strict_types=1);

namespace App\ValueObject\Combat;

/**
 * Value object representing axial coordinates (q, r) on a flat-topped hexagonal grid.
 */
final readonly class HexCoordinate
{
    public function __construct(
        public int $q,
        public int $r,
    ) {
    }

    /**
     * Calculate axial distance between this coordinate and another.
     */
    public function distance(self $other): int
    {
        return (int) (
            (abs($this->q - $other->q)
            + abs($this->q + $this->r - $other->q - $other->r)
            + abs($this->r - $other->r)) / 2
        );
    }

    /**
     * Get the 6 adjacent neighbor coordinates on a flat-topped hex grid.
     *
     * @return list<self>
     */
    public function getNeighbors(): array
    {
        $directions = [
            [1, 0], [1, -1], [0, -1],
            [-1, 0], [-1, 1], [0, 1],
        ];

        $neighbors = [];
        foreach ($directions as [$dq, $dr]) {
            $neighbors[] = new self($this->q + $dq, $this->r + $dr);
        }

        return $neighbors;
    }

    /**
     * Check if an attacker position is in the rear arc (flanking) of a target facing a given direction.
     * $facingDirection: 1 for facing right (towards q+), -1 for facing left (towards q-).
     */
    public function isRearArc(self $target, int $facingDirection = 1): bool
    {
        if ($facingDirection >= 0) {
            // Target is facing Right (towards positive Q)
            return $this->q < $target->q;
        }

        // Target is facing Left (towards negative Q)
        return $this->q > $target->q;
    }

    /**
     * @return array{q: int, r: int}
     */
    public function toArray(): array
    {
        return [
            'q' => $this->q,
            'r' => $this->r,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self((int) $data['q'], (int) $data['r']);
    }

    public function equals(self $other): bool
    {
        return $this->q === $other->q && $this->r === $other->r;
    }

    public function key(): string
    {
        return sprintf('%d,%d', $this->q, $this->r);
    }
}
