<?php

declare(strict_types=1);

namespace App\Service\League;

use App\Entity\League\LeagueSeason;
use App\Entity\League\LeagueTier;

/**
 * Locked league shape (#18). A kingdom is created as this pyramid and every
 * later season copies the season that just finished. Group size is 10 because
 * the 77-day calendar has 18 kickoffs, a double round-robin of 10 teams.
 */
final class LeagueStructure
{
    public const TEAMS_PER_GROUP = 10;

    public const SEASON_LENGTH_DAYS = 77;

    /** Single round-robin length for {@see TEAMS_PER_GROUP} teams. */
    public const SINGLE_LEG_ROUNDS = 9;

    /** Double round-robin. Weeks 2–10, Tuesday and Friday. */
    public const ROUNDS = 18;

    /**
     * @var list<array{name: string, groups: int, promotion_slots: int, relegation_slots: int}>
     */
    public const TIERS = [
        ['name' => 'T1', 'groups' => 1, 'promotion_slots' => 0, 'relegation_slots' => 4],
        ['name' => 'T2', 'groups' => 2, 'promotion_slots' => 4, 'relegation_slots' => 6],
        ['name' => 'T3', 'groups' => 3, 'promotion_slots' => 6, 'relegation_slots' => 0],
    ];

    /**
     * @param array<string, mixed> $config
     */
    public static function assertCreationConfig(array $config): void
    {
        if (self::TEAMS_PER_GROUP !== (int) ($config['teams_per_group'] ?? 0)) {
            throw new \DomainException('league_tiers_config must use 10 teams per group.');
        }

        $tiers = $config['tiers'] ?? null;
        if (!is_array($tiers) || count(self::TIERS) !== count($tiers)) {
            throw new \DomainException('league_tiers_config must contain exactly the T1, T2, and T3 tiers.');
        }

        foreach (self::TIERS as $index => $expected) {
            $tier = $tiers[$index] ?? null;
            if (!is_array($tier) || !self::tierMatches($tier, $expected)) {
                throw new \DomainException(sprintf('league_tiers_config tier %d must be %s with %d groups, %d promotion slots, and %d relegation slots.', $index + 1, $expected['name'], $expected['groups'], $expected['promotion_slots'], $expected['relegation_slots']));
            }
        }
    }

    public static function assertSeasonLength(int $seasonLength): void
    {
        if (self::SEASON_LENGTH_DAYS !== $seasonLength) {
            throw new \DomainException('Kingdom season_length must be 77 days.');
        }
    }

    public static function assertSameShape(LeagueSeason $current, LeagueSeason $upcoming): void
    {
        $currentTiers = self::sortedTiers($current);
        $upcomingTiers = self::sortedTiers($upcoming);

        if (count($currentTiers) !== count($upcomingTiers)) {
            throw new \RuntimeException('Mismatch between current and upcoming season tier counts.');
        }

        foreach ($currentTiers as $index => $currentTier) {
            $upcomingTier = $upcomingTiers[$index];
            if ($currentTier->getGroups()->count() !== $upcomingTier->getGroups()->count()) {
                throw new \RuntimeException('Mismatch between current and upcoming season group counts.');
            }
            if ($currentTier->getPromotionSlots() !== $upcomingTier->getPromotionSlots()
                || $currentTier->getRelegationSlots() !== $upcomingTier->getRelegationSlots()
            ) {
                throw new \RuntimeException('Mismatch between current and upcoming season promotion or relegation slots.');
            }
        }
    }

    /**
     * @return list<LeagueTier>
     */
    public static function sortedTiers(LeagueSeason $season): array
    {
        $tiers = $season->getTiers()->toArray();
        usort($tiers, static fn (LeagueTier $a, LeagueTier $b): int => $a->getId() <=> $b->getId());

        return $tiers;
    }

    /**
     * @param array<string, mixed>                                                          $tier
     * @param array{name: string, groups: int, promotion_slots: int, relegation_slots: int} $expected
     */
    private static function tierMatches(array $tier, array $expected): bool
    {
        $groups = $tier['groups'] ?? null;
        $groupCount = is_array($groups) ? count($groups) : (int) $groups;

        return $expected['name'] === (string) ($tier['name'] ?? '')
            && $expected['groups'] === $groupCount
            && $expected['promotion_slots'] === (int) ($tier['promotion_slots'] ?? -1)
            && $expected['relegation_slots'] === (int) ($tier['relegation_slots'] ?? -1);
    }
}
