<?php

declare(strict_types=1);

namespace App\ValueObject\Combat;

use App\Enum\BattleResult;

final class MatchOutcome
{
    public function __construct(
        private int $homeScore,
        private int $awayScore,
        private bool $isForfeit = false,
    ) {
    }

    public static function forfeit(int $homeScore, int $awayScore): self
    {
        return new self($homeScore, $awayScore, true);
    }

    public function getHomeScore(): int
    {
        return $this->homeScore;
    }

    public function getAwayScore(): int
    {
        return $this->awayScore;
    }

    public function isForfeit(): bool
    {
        return $this->isForfeit;
    }

    public function toBattleResult(): BattleResult
    {
        if ($this->homeScore > $this->awayScore) {
            return BattleResult::WinA;
        }
        if ($this->homeScore < $this->awayScore) {
            return BattleResult::WinB;
        }

        return BattleResult::Draw;
    }
}
