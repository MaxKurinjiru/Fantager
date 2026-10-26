<?php

declare(strict_types=1);

namespace App\Service\Combat;

use App\Enum\StatusEffect;

/**
 * Combat identity for a spell row: optional instant HP plus optional status.
 */
final class SpellPayload
{
    public const INSTANT_DAMAGE = 'damage';

    public const INSTANT_HEAL = 'heal';

    public function __construct(
        public readonly ?string $instantHp,
        public readonly ?StatusEffect $applyEffect,
    ) {
    }

    /**
     * @param array<string, mixed> $effects
     */
    public static function fromEffects(array $effects): self
    {
        $instant = $effects['instant_hp'] ?? null;
        $instantHp = \is_string($instant) && \in_array($instant, [self::INSTANT_DAMAGE, self::INSTANT_HEAL], true)
            ? $instant
            : null;

        $status = null;
        $rawStatus = $effects['apply_effect'] ?? null;
        if (\is_string($rawStatus) && '' !== $rawStatus) {
            $status = StatusEffect::tryFrom($rawStatus);
        }

        return new self($instantHp, $status);
    }

    /**
     * Catalog JSON. Combat reads the same keys.
     *
     * @return array<string, string>
     */
    public static function catalog(?string $instantHp, ?StatusEffect $status): array
    {
        if (null === $instantHp && null === $status) {
            throw new \InvalidArgumentException('Spell payload must have instant_hp or apply_effect.');
        }
        if (null !== $instantHp && !\in_array($instantHp, [self::INSTANT_DAMAGE, self::INSTANT_HEAL], true)) {
            throw new \InvalidArgumentException('instant_hp must be damage or heal.');
        }

        $effects = [];
        if (null !== $instantHp) {
            $effects['instant_hp'] = $instantHp;
        }
        if (null !== $status) {
            $effects['apply_effect'] = $status->value;
        }

        return $effects;
    }

    public function requiresMagicalWeapon(): bool
    {
        return self::INSTANT_DAMAGE === $this->instantHp;
    }
}
