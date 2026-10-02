<?php

declare(strict_types=1);

namespace App\ValueObject\Combat;

use App\Enum\BattleResult;

final class MatchOutcome
{
    /**
     * @param array<string, mixed> $combatLog
     */
    public function __construct(
        private int $homeScore,
        private int $awayScore,
        private bool $isForfeit = false,
        private array $combatLog = [],
        private ?int $seed = null,
    ) {
    }

    public static function forfeit(int $homeScore, int $awayScore): self
    {
        return new self(
            $homeScore,
            $awayScore,
            true,
            self::forfeitLog($homeScore, $awayScore),
            null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function forfeitLog(int $homeScore, int $awayScore): array
    {
        return [
            'version' => 1,
            'simulator' => 'forfeit',
            'events' => [],
            'result' => [
                'score_a' => $homeScore,
                'score_b' => $awayScore,
            ],
        ];
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

    /** @return array<string, mixed> */
    public function getCombatLog(): array
    {
        return $this->combatLog;
    }

    public function getSeed(): ?int
    {
        return $this->seed;
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
