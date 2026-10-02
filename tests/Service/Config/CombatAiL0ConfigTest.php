<?php

declare(strict_types=1);

namespace App\Tests\Service\Config;

use App\Enum\FormationApproach;
use App\Service\Config\CombatAiL0Config;
use PHPUnit\Framework\TestCase;

class CombatAiL0ConfigTest extends TestCase
{
    public function testYamlThresholdsMatchTheL0Contract(): void
    {
        $config = new CombatAiL0Config(\dirname(__DIR__, 3));

        $this->assertSame(25, $config->healThresholdPercent(FormationApproach::Aggressive));
        $this->assertSame(40, $config->healThresholdPercent(FormationApproach::Balanced));
        $this->assertSame(50, $config->healThresholdPercent(FormationApproach::Defensive));
    }
}
