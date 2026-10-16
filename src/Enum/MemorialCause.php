<?php

declare(strict_types=1);

namespace App\Enum;

enum MemorialCause: string
{
    case Dismissed = 'dismissed';
    case CombatDeath = 'combat_death';

    /** Reserved / unused (A6.2). No death-of-old-age tick. Do not write this case. */
    case Age = 'age';

    /** Reserved / unused (A6.2). No retirement writer. Do not write this case. */
    case Retired = 'retired';

    /** Reserved / unused (A6.2). Live combat deaths use CombatDeath. Do not write this case. */
    case Death = 'death';

    /**
     * Reserved cases stay on the enum (and DB column) but must not be written.
     */
    public function isReserved(): bool
    {
        return match ($this) {
            self::Age, self::Retired, self::Death => true,
            default => false,
        };
    }

    /**
     * Player-facing filter options — excludes reserved cases.
     *
     * @return list<self>
     */
    public static function liveCases(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $case): bool => !$case->isReserved()));
    }
}
