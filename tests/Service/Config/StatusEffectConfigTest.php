<?php

declare(strict_types=1);

namespace App\Tests\Service\Config;

use App\Enum\School;
use App\Enum\StatusEffect;
use App\Service\Config\StatusEffectConfig;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class StatusEffectConfigTest extends KernelTestCase
{
    private StatusEffectConfig $config;

    protected function setUp(): void
    {
        self::bootKernel();
        /** @var StatusEffectConfig $config */
        $config = self::getContainer()->get(StatusEffectConfig::class);
        $this->config = $config;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    public function testGetReturnsCorrectDefinition(): void
    {
        $definition = $this->config->get(StatusEffect::Burn);

        $this->assertSame(StatusEffect::Burn, $definition->getKey());
        $this->assertSame('Burn', $definition->getName());
        $this->assertSame('debuff', $definition->getType());
        $this->assertSame(School::Fire, $definition->getSchool());
        $this->assertSame(3, $definition->getDurationTurns());
        $this->assertSame(5, $definition->getTickDamagePercent());
        $this->assertFalse($definition->isStackable());
    }

    public function testGetRegenerationDefinition(): void
    {
        $definition = $this->config->get(StatusEffect::Regeneration);

        $this->assertSame(StatusEffect::Regeneration, $definition->getKey());
        $this->assertSame('Regeneration', $definition->getName());
        $this->assertSame('buff', $definition->getType());
        $this->assertSame(School::Water, $definition->getSchool());
        $this->assertSame(3, $definition->getDurationTurns());
        $this->assertSame(5, $definition->getTickHealPercent());
        $this->assertFalse($definition->isStackable());
    }

    public function testAllReturnsAllDefinitions(): void
    {
        $all = $this->config->all();

        $this->assertCount(16, $all);
        $this->assertArrayHasKey('burn', $all);
        $this->assertArrayHasKey('regeneration', $all);
        $this->assertArrayHasKey('silence', $all);
    }
}
