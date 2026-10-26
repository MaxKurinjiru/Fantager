<?php

declare(strict_types=1);

namespace App\Tests\Service\Combat;

use App\Enum\StatusEffect;
use App\Service\Combat\SpellPayload;
use PHPUnit\Framework\TestCase;

class SpellPayloadTest extends TestCase
{
    public function testCatalogRequiresAtLeastOnePayload(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SpellPayload::catalog(null, null);
    }

    public function testCatalogAndFromEffectsRoundTrip(): void
    {
        $effects = SpellPayload::catalog(SpellPayload::INSTANT_DAMAGE, StatusEffect::Burn);
        $this->assertSame(
            ['instant_hp' => 'damage', 'apply_effect' => 'burn'],
            $effects
        );

        $payload = SpellPayload::fromEffects($effects);
        $this->assertSame('damage', $payload->instantHp);
        $this->assertSame(StatusEffect::Burn, $payload->applyEffect);
        $this->assertTrue($payload->requiresMagicalWeapon());
    }

    public function testHealAndStatusOnlyDoNotNeedAWand(): void
    {
        $heal = SpellPayload::fromEffects(SpellPayload::catalog(SpellPayload::INSTANT_HEAL, null));
        $this->assertFalse($heal->requiresMagicalWeapon());

        $status = SpellPayload::fromEffects(SpellPayload::catalog(null, StatusEffect::Freeze));
        $this->assertFalse($status->requiresMagicalWeapon());
        $this->assertNull($status->instantHp);
    }
}
